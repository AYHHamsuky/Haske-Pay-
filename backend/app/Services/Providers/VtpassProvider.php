<?php

namespace App\Services\Providers;

use App\Jobs\SendSms;
use App\Models\Service;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * VTpass API: https://www.vtpass.com/documentation/
 * POST calls use api-key + secret-key headers, GET calls use api-key + public-key.
 */
class VtpassProvider implements BillProvider
{
    use HandlesHttpErrors;

    // https://vtpass.com/documentation/response-codes/
    // Codes that mean VTpass refused the request or reversed it: the customer is refunded.
    private const FAILED_CODES = ['010', '011', '012', '013', '016', '017', '018', '019', '021', '022', '023', '024', '025',
        '026', '027', '028', '030', '031', '032', '034', '035', '040', '085', '087', '091'];

    // Failures caused by our own VTpass account, not the customer: page the team immediately.
    private const OPERATOR_CODES = [
        '018' => 'VTpass wallet balance is too low. Top up the VTpass wallet.',
        '021' => 'VTpass account is locked.',
        '022' => 'VTpass account is suspended.',
        '023' => 'API access is not enabled on the VTpass account. Ask VTpass to activate it.',
        '024' => 'VTpass account is inactive.',
        '027' => 'Server IP is not whitelisted on VTpass. Send the server IP to VTpass support.',
        '028' => 'Product is not whitelisted on the VTpass account.',
        '085' => 'request_id was rejected by VTpass: check the server clock and timezone.',
        '087' => 'VTpass rejected the API keys. Check VTPASS_API_KEY / SECRET / PUBLIC in .env.',
    ];

    private const NOT_FOUND_CODES = ['015'];

    // Verify returns 000, or 020 "BILLER CONFIRMED".
    private const VERIFY_OK_CODES = ['000', '020'];

    public function __construct(private readonly array $cfg) {}

    public function name(): string
    {
        return 'vtpass';
    }

    private function post(): PendingRequest
    {
        return Http::baseUrl($this->cfg['base_url'])
            ->withHeaders(['api-key' => $this->cfg['api_key'], 'secret-key' => $this->cfg['secret_key']])
            ->acceptJson()->asJson()
            ->connectTimeout($this->cfg['connect_timeout'])
            ->timeout($this->cfg['timeout']);
    }

    private function get(): PendingRequest
    {
        return Http::baseUrl($this->cfg['base_url'])
            ->withHeaders(['api-key' => $this->cfg['api_key'], 'public-key' => $this->cfg['public_key']])
            ->acceptJson()
            ->connectTimeout($this->cfg['connect_timeout'])
            ->timeout(20);
    }

    public function verifyMeter(Service $service, string $meter, string $meterType): VerifyResult
    {
        try {
            $res = $this->decode($this->post()->timeout(25)->post('/merchant-verify', [
                'billersCode' => $meter,
                'serviceID' => $service->code,
                'type' => $meterType,
            ]));
        } catch (ConnectionException $e) {
            Log::warning('vtpass.verify.network', ['error' => $e->getMessage()]);

            return VerifyResult::notFound('We could not reach the electricity company. Try again in a minute.');
        }

        $this->alertIfOperatorProblem($res);
        if (isset(self::OPERATOR_CODES[(string) ($res['code'] ?? '')])) {
            // Our account is the problem, not the meter; don't tell the customer their meter is wrong.
            return VerifyResult::notFound('Meter lookups are temporarily unavailable. Please try again shortly. [VTpass '.$res['code'].']');
        }
        $c = $res['content'] ?? [];
        $wrong = filter_var($c['WrongBillersCode'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! in_array((string) ($res['code'] ?? ''), self::VERIFY_OK_CODES, true) || ! empty($c['error']) || $wrong || empty($c['Customer_Name'])) {
            return VerifyResult::notFound();
        }
        if (isset($c['Can_Vend']) && strtolower((string) $c['Can_Vend']) === 'no') {
            return VerifyResult::notFound('This meter cannot be vended right now. Ask the customer to contact their DisCo.');
        }

        $min = collect([$c['Minimum_Amount'] ?? null, $c['Min_Purchase_Amount'] ?? null])
            ->first(fn ($v) => is_numeric($v) && $v > 0);

        return VerifyResult::found(
            trim($c['Customer_Name']),
            isset($c['Address']) ? trim($c['Address']) : null,
            null, // VTpass sends the account type (PRIME, MD, NMD), not a tariff band
            $min !== null ? Money::fromNaira($min) : null,
        );
    }

    public function purchase(Transaction $tx): ProviderResult
    {
        $payload = ['request_id' => $tx->request_id, 'serviceID' => $tx->service_code, 'amount' => $tx->amount / 100];

        match ($tx->category) {
            'electricity' => $payload += [
                'billersCode' => $tx->target,
                'variation_code' => $tx->meter_type,
                'phone' => $tx->customer_phone ?? $tx->user->phone,
            ],
            'airtime' => $payload += ['phone' => $tx->target],
            'data' => $payload += [
                'billersCode' => $tx->target,
                'variation_code' => $tx->variation_code,
                'phone' => $tx->target,
            ],
        };

        $tx->forceFill(['request_payload' => $payload])->save();

        try {
            $res = $this->post()->post('/pay', $payload);
        } catch (ConnectionException $e) {
            return $this->classifyNetworkError($e);
        }
        if ($res->serverError()) {
            // A 5xx may have been processed upstream. Requery decides.
            return ProviderResult::pending('VTpass HTTP '.$res->status(), ['status' => $res->status()]);
        }

        return $this->mapResponse($this->decode($res));
    }

    public function requery(Transaction $tx): ProviderResult
    {
        try {
            $res = $this->post()->timeout(25)->post('/requery', ['request_id' => $tx->request_id]);
        } catch (ConnectionException $e) {
            return ProviderResult::pending('VTpass requery failed: '.$e->getMessage());
        }

        $json = $this->decode($res);
        if (in_array((string) ($json['code'] ?? ''), self::NOT_FOUND_CODES, true)) {
            return ProviderResult::notFound($json);
        }

        return $this->mapResponse($json);
    }

    private function mapResponse(array $json): ProviderResult
    {
        $this->alertIfOperatorProblem($json);
        $code = (string) ($json['code'] ?? '');
        $txInfo = $json['content']['transactions'] ?? [];
        $status = strtolower((string) ($txInfo['status'] ?? ''));
        $ref = isset($txInfo['transactionId']) ? (string) $txInfo['transactionId'] : null;
        $desc = $json['response_description'] ?? $json['message'] ?? null;

        if ($code === '000' && $status === 'delivered') {
            return ProviderResult::success($ref, $this->extractToken($json), $this->extractUnits($json), $json);
        }
        if (in_array($status, ['failed', 'reversed'], true) || in_array($code, self::FAILED_CODES, true)) {
            return ProviderResult::failed($desc ?: 'The provider declined the transaction.', $json);
        }

        // 000 + pending/initiated, 099 processing, 014 duplicate request id, or anything unrecognised.
        return ProviderResult::pending($desc, $json, $ref);
    }

    /**
     * VTpass usually answers with a JSON object, but some auth failures come back as a bare
     * JSON string ("Invalid Credentials."). Normalise both to an array with a code.
     */
    private function decode(Response $res): array
    {
        $body = $res->json();
        if (is_array($body)) {
            return $body;
        }
        $text = is_string($body) ? $body : $res->body();

        return [
            'code' => str_contains(strtolower($text), 'credential') ? '087' : null,
            'message' => trim($text) ?: 'HTTP '.$res->status(),
        ];
    }

    private function alertIfOperatorProblem(array $json): void
    {
        $code = (string) ($json['code'] ?? '');
        if (! isset(self::OPERATOR_CODES[$code])) {
            return;
        }
        Log::critical('vtpass.operator', ['code' => $code, 'problem' => self::OPERATOR_CODES[$code]]);
        // One SMS per problem per 30 minutes, not one per failed purchase.
        if (Cache::add("alert.vtpass.{$code}", true, now()->addMinutes(30))) {
            foreach (config('haske.alerts.phones') as $phone) {
                SendSms::dispatch($phone, "Haske ALERT (VTpass {$code}): ".self::OPERATOR_CODES[$code]);
            }
        }
    }

    private function extractToken(array $json): ?string
    {
        // VTpass sends e.g. "token": "Token : 26362054405982757802"; keep the digits.
        foreach (['token', 'mainToken', 'Token'] as $k) {
            if (! empty($json[$k]) && is_scalar($json[$k])) {
                return preg_replace('/\D/', '', (string) $json[$k]) ?: null;
            }
        }
        // "purchased_code": "Token : 1234 5678 9012 3456 7890"
        if (! empty($json['purchased_code']) && preg_match('/(\d[\d\s-]{15,}\d)/', $json['purchased_code'], $m)) {
            return preg_replace('/\D/', '', $m[1]);
        }

        return null;
    }

    private function extractUnits(array $json): ?string
    {
        foreach (['units', 'mainTokenUnits', 'Units'] as $k) {
            if (! empty($json[$k]) && is_scalar($json[$k])) {
                // Some DisCos send "79.9 kWh", others a bare "33.20".
                return is_numeric($json[$k]) ? $json[$k].' kWh' : (string) $json[$k];
            }
        }

        return null;
    }

    public function dataPlans(Service $service): array
    {
        $key = "vtpass.plans.{$service->code}";
        if ($cached = Cache::get($key)) {
            return $cached;
        }
        $json = $this->get()->get('/service-variations', ['serviceID' => $service->code])->throw();
        $json = $this->decode($json);
        $this->alertIfOperatorProblem($json);
        // The docs return the list under both "variations" and the misspelt "varations".
        $list = $json['content']['variations'] ?? $json['content']['varations'] ?? [];
        if (! $list) {
            throw new RuntimeException('VTpass returned no plans: '.($json['response_description'] ?? $json['message'] ?? $json['code'] ?? 'empty response'));
        }
        $plans = collect($list)->map(fn ($v) => [
            'code' => (string) $v['variation_code'],
            'name' => (string) $v['name'],
            'amount' => Money::fromNaira($v['variation_amount']),
        ])->values()->all();
        Cache::put($key, $plans, now()->addHours(6));

        return $plans;
    }

    public function balance(): ?int
    {
        try {
            $json = $this->decode($this->get()->get('/balance'));
        } catch (ConnectionException) {
            return null;
        }
        $bal = $json['contents']['balance'] ?? $json['content']['balance'] ?? null;

        return is_numeric($bal) ? Money::fromNaira($bal) : null;
    }
}
