<?php

namespace App\Services\Providers;

use App\Models\Service;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Baxi (Capricorn) backup route. Our request_id is sent as agentReference so a
 * requery or retry can never vend twice.
 *
 * Baxi service types live in services.meta: baxi_service (e.g. kaduna_electric),
 * suffixed with _prepaid / _postpaid for electricity. Confirm the exact names
 * against the Baxi sandbox when the account is opened.
 */
class BaxiProvider implements BillProvider
{
    use HandlesHttpErrors;

    public function __construct(private readonly array $cfg) {}

    public function name(): string
    {
        return 'baxi';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->cfg['base_url'])
            ->withHeaders(['x-api-key' => (string) $this->cfg['api_key']])
            ->acceptJson()->asJson()
            ->connectTimeout($this->cfg['connect_timeout'])
            ->timeout($this->cfg['timeout']);
    }

    private function serviceType(Service $service, ?string $meterType = null): string
    {
        $base = $service->meta['baxi_service'] ?? str_replace('-', '_', $service->code);

        return $meterType ? "{$base}_{$meterType}" : $base;
    }

    public function verifyMeter(Service $service, string $meter, string $meterType): VerifyResult
    {
        try {
            $json = $this->http()->timeout(25)->post('/services/namefinder/query', [
                'service_type' => $this->serviceType($service, $meterType),
                'account_number' => $meter,
            ])->json() ?? [];
        } catch (ConnectionException) {
            return VerifyResult::notFound('We could not reach the electricity company. Try again in a minute.');
        }
        $user = $json['data']['user'] ?? null;
        if (($json['status'] ?? null) !== 'success' || empty($user['name'])) {
            return VerifyResult::notFound();
        }

        return VerifyResult::found(trim($user['name']), $user['address'] ?? null);
    }

    public function purchase(Transaction $tx): ProviderResult
    {
        $base = ['agentReference' => $tx->request_id, 'agentId' => $this->cfg['agent_id'], 'amount' => $tx->amount / 100];
        [$path, $payload] = match ($tx->category) {
            'electricity' => ['/services/electricity/request', $base + [
                'service_type' => $this->serviceType($tx->service, $tx->meter_type),
                'account_number' => $tx->target,
                'phone' => $tx->customer_phone ?? $tx->user->phone,
            ]],
            'airtime' => ['/services/airtime/request', $base + [
                'service_type' => $this->serviceType($tx->service),
                'plan' => 'prepaid',
                'phone' => $tx->target,
            ]],
            'data' => ['/services/databundle/request', $base + [
                'service_type' => $this->serviceType($tx->service),
                'datacode' => $tx->variation_code,
                'phone' => $tx->target,
            ]],
        };

        try {
            $res = $this->http()->post($path, $payload);
        } catch (ConnectionException $e) {
            return $this->classifyNetworkError($e);
        }
        if ($res->serverError()) {
            return ProviderResult::pending('Baxi HTTP '.$res->status());
        }

        return $this->map($res->json() ?? []);
    }

    public function requery(Transaction $tx): ProviderResult
    {
        try {
            $res = $this->http()->timeout(25)->get('/superagent/transaction/requery', ['agentReference' => $tx->request_id]);
        } catch (ConnectionException $e) {
            return ProviderResult::pending('Baxi requery failed: '.$e->getMessage());
        }
        if ($res->status() === 404) {
            return ProviderResult::notFound($res->json() ?? []);
        }

        return $this->map($res->json() ?? []);
    }

    private function map(array $json): ProviderResult
    {
        $data = $json['data'] ?? [];
        $state = strtolower((string) ($data['transactionStatus'] ?? $json['status'] ?? ''));
        $ref = isset($data['transactionReference']) ? (string) $data['transactionReference'] : null;

        if ($state === 'success' && (($json['status'] ?? '') === 'success')) {
            $token = isset($data['tokenCode']) ? preg_replace('/\D/', '', (string) $data['tokenCode']) : null;

            return ProviderResult::success($ref, $token ?: null, isset($data['amountOfPower']) ? (string) $data['amountOfPower'] : null, $json);
        }
        if (in_array($state, ['failed', 'error', 'reversed'], true) || (($json['status'] ?? '') === 'error' && ($json['code'] ?? 0) < 500)) {
            return ProviderResult::failed($json['message'] ?? 'The backup provider declined the transaction.', $json);
        }

        return ProviderResult::pending($json['message'] ?? null, $json, $ref);
    }

    public function dataPlans(Service $service): array
    {
        return Cache::remember("baxi.plans.{$service->code}", now()->addHours(6), function () use ($service) {
            $json = $this->http()->post('/services/databundle/bundles', ['service_type' => $this->serviceType($service)])->throw()->json();

            return collect($json['data'] ?? [])->map(fn ($p) => [
                'code' => (string) $p['datacode'],
                'name' => (string) $p['name'],
                'amount' => Money::fromNaira($p['price']),
            ])->values()->all();
        });
    }

    public function balance(): ?int
    {
        try {
            $json = $this->http()->timeout(20)->get('/superagent/account/balance')->json();
        } catch (ConnectionException) {
            return null;
        }
        $bal = $json['data']['balance'] ?? null;

        return is_numeric($bal) ? Money::fromNaira($bal) : null;
    }
}
