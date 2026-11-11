<?php

namespace Tests\Unit;

use App\Jobs\SendSms;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\VtpassProvider;
use App\Support\References;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Response shapes copied from https://vtpass.com/documentation/ (IKEDC, MTN data, response codes, balance).
 */
class VtpassProviderTest extends TestCase
{
    private function vt(): VtpassProvider
    {
        return new VtpassProvider([
            'base_url' => 'https://sandbox.vtpass.com/api', 'api_key' => 'ak', 'public_key' => 'PK_x', 'secret_key' => 'SK_x',
            'connect_timeout' => 5, 'timeout' => 45,
        ]);
    }

    private function tx(array $attrs = []): Transaction
    {
        $user = $this->customer();
        $tx = new Transaction($attrs + [
            'reference' => 'HP-TEST1', 'request_id' => '202610311230ABCDEF', 'user_id' => $user->id, 'service_code' => 'ikeja-electric',
            'category' => 'electricity', 'target' => '1111111111111', 'meter_type' => 'prepaid', 'amount' => 2_000_00, 'provider' => 'vtpass',
        ]);
        $tx->setRelation('user', $user);
        $tx->setRelation('service', Service::byCode($tx->service_code));
        $tx->save();

        return $tx;
    }

    public function test_verify_uses_secret_key_and_reads_customer(): void
    {
        Http::fake(['*/merchant-verify' => Http::response(['code' => '000', 'content' => [
            'Customer_Name' => 'TESTMETER1', 'Address' => 'ABULE - EGBA BU ABULE', 'Meter_Number' => '1111111111111', 'Customer_Arrears' => '',
            'Minimum_Amount' => '', 'Min_Purchase_Amount' => 1000, 'Can_Vend' => 'yes', 'Customer_Account_Type' => 'PRIME', 'WrongBillersCode' => false,
        ]])]);

        $r = $this->vt()->verifyMeter(Service::byCode('ikeja-electric'), '1111111111111', 'prepaid');

        $this->assertTrue($r->ok);
        $this->assertSame('TESTMETER1', $r->name);
        $this->assertNull($r->band, 'account type is not a tariff band');
        $this->assertSame(1_000_00, $r->minAmount);
        Http::assertSent(fn (Request $req) => $req->hasHeader('api-key', 'ak') && $req->hasHeader('secret-key', 'SK_x')
            && $req['billersCode'] === '1111111111111' && $req['serviceID'] === 'ikeja-electric' && $req['type'] === 'prepaid');
    }

    public function test_verify_accepts_020_and_rejects_wrong_or_unvendable_meters(): void
    {
        $svc = Service::byCode('ikeja-electric');
        Http::fakeSequence()
            ->push(['code' => '020', 'content' => ['Customer_Name' => 'A', 'WrongBillersCode' => false]])
            ->push(['code' => '000', 'content' => ['WrongBillersCode' => true, 'error' => 'Meter not found']])
            ->push(['code' => '000', 'content' => ['Customer_Name' => 'B', 'Can_Vend' => 'no', 'WrongBillersCode' => false]]);

        $this->assertTrue($this->vt()->verifyMeter($svc, '1', 'prepaid')->ok);
        $this->assertFalse($this->vt()->verifyMeter($svc, '2', 'prepaid')->ok);
        $this->assertStringContainsString('cannot be vended', $this->vt()->verifyMeter($svc, '3', 'prepaid')->error);
    }

    public function test_prepaid_delivery_extracts_token_digits_and_units(): void
    {
        Http::fake(['*/pay' => Http::response([
            'code' => '000', 'response_description' => 'TRANSACTION SUCCESSFUL', 'requestId' => '202610311230ABCDEF', 'amount' => 2000,
            'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => '17415980564672211596777904']],
            'purchased_code' => 'Token : 26362054405982757802', 'token' => 'Token : 26362054405982757802', 'units' => '79.9 kWh',
        ])]);

        $r = $this->vt()->purchase($this->tx());

        $this->assertSame(ProviderResult::SUCCESS, $r->status);
        $this->assertSame('26362054405982757802', $r->token);
        $this->assertSame('79.9 kWh', $r->units);
        Http::assertSent(fn (Request $req) => $req['variation_code'] === 'prepaid' && $req['amount'] == 2000 && $req['billersCode'] === '1111111111111');
    }

    public function test_status_mapping_follows_the_response_code_table(): void
    {
        $cases = [
            [['code' => '000', 'content' => ['transactions' => ['status' => 'pending']]], ProviderResult::PENDING],
            [['code' => '000', 'content' => ['transactions' => ['status' => 'initiated']]], ProviderResult::PENDING],
            [['code' => '099', 'response_description' => 'TRANSACTION IS PROCESSING'], ProviderResult::PENDING],
            [['code' => '014', 'response_description' => 'REQUEST ID ALREADY EXIST'], ProviderResult::PENDING],
            [['code' => '083', 'response_description' => 'SYSTEM ERROR'], ProviderResult::PENDING],
            [['code' => '016', 'response_description' => 'TRANSACTION FAILED'], ProviderResult::FAILED],
            [['code' => '040', 'response_description' => 'TRANSACTION REVERSAL'], ProviderResult::FAILED],
            [['code' => '010', 'response_description' => 'VARIATION CODE DOES NOT EXIST'], ProviderResult::FAILED],
            [['code' => '091', 'response_description' => 'TRANSACTION NOT PROCESSED'], ProviderResult::FAILED],
        ];
        $seq = Http::fakeSequence();
        foreach ($cases as [$json]) {
            $seq->push($json);
        }
        foreach ($cases as $i => [$json, $want]) {
            $this->assertSame($want, $this->vt()->purchase($this->tx(['reference' => "HP-T{$i}", 'request_id' => "202610311230X{$i}"]))->status, 'code '.$json['code']);
        }
    }

    public function test_bad_keys_fail_the_purchase_and_page_the_team_once(): void
    {
        Bus::fake();
        config(['haske.alerts.phones' => ['08010000001']]);
        Http::fake(['*' => Http::response(['code' => '087', 'message' => 'INVALID CREDENTIALS'])]);

        $this->assertSame(ProviderResult::FAILED, $this->vt()->purchase($this->tx())->status);
        $this->vt()->purchase($this->tx(['reference' => 'HP-T2', 'request_id' => '202610311230Y']));

        Bus::assertDispatchedTimes(SendSms::class, 1);
    }

    public function test_bare_string_invalid_credentials_is_treated_as_087(): void
    {
        Bus::fake();
        Http::fake(['*' => Http::response('"Invalid Credentials."', 200, ['Content-Type' => 'application/json'])]);

        $this->assertNull($this->vt()->balance());
        $this->assertStringContainsString('[VTpass 087]', $this->vt()->verifyMeter(Service::byCode('ikeja-electric'), '1111111111111', 'prepaid')->error);
        $this->assertSame(ProviderResult::FAILED, $this->vt()->purchase($this->tx())->status);
    }

    public function test_requery_unknown_request_is_not_found(): void
    {
        Http::fake(['*/requery' => Http::response(['code' => '015', 'response_description' => 'INVALID REQUEST ID'])]);

        $this->assertSame(ProviderResult::NOT_FOUND, $this->vt()->requery($this->tx())->status);
    }

    public function test_connection_refused_is_unreachable_but_a_lost_response_is_pending(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            throw new ConnectionException(++$calls === 1
                ? 'cURL error 7: Failed to connect to sandbox.vtpass.com port 443'
                : 'cURL error 28: Operation timed out after 45001 milliseconds with 0 bytes received');
        });
        $this->assertSame(ProviderResult::UNREACHABLE, $this->vt()->purchase($this->tx())->status);

        $this->assertSame(ProviderResult::PENDING, $this->vt()->purchase($this->tx(['reference' => 'HP-T2', 'request_id' => '202610311230Z']))->status);
    }

    public function test_balance_and_data_plans_use_public_key(): void
    {
        Http::fake([
            '*/balance' => Http::response(['code' => 1, 'contents' => ['balance' => 1081.8199999998]]),
            '*/service-variations*' => Http::response(['response_description' => '000', 'content' => [
                'ServiceName' => 'MTN Data', 'serviceID' => 'mtn-data',
                'varations' => [['variation_code' => 'mtn-10mb-100', 'name' => 'N100 100MB - 24 hrs', 'variation_amount' => '100.00', 'fixedPrice' => 'Yes']],
            ]]),
        ]);

        $this->assertSame(1_081_82, $this->vt()->balance());
        $plans = $this->vt()->dataPlans(Service::byCode('mtn-data'));
        $this->assertSame([['code' => 'mtn-10mb-100', 'name' => 'N100 100MB - 24 hrs', 'amount' => 100_00]], $plans);
        Http::assertSent(fn (Request $req) => $req->method() === 'GET' && $req->hasHeader('public-key', 'PK_x') && ! $req->hasHeader('secret-key'));
    }

    public function test_request_id_starts_with_lagos_time(): void
    {
        $this->travelTo(now('UTC')->setTime(23, 30));
        $rid = References::vtpassRequestId();

        // 23:30 UTC is 00:30 the next day in Lagos (GMT+1).
        $this->assertStringStartsWith(now('Africa/Lagos')->format('YmdHi'), $rid);
        $this->assertMatchesRegularExpression('/^\d{12}[A-Z0-9]+$/', $rid);
        $this->assertGreaterThanOrEqual(12, strlen($rid));
    }
}
