<?php

namespace App\Services;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\PurchaseRejected;
use App\Jobs\SendSms;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
use App\Support\Money;
use App\Support\References;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purchase flow:
 *   validate -> debit wallet -> call provider -> success | refund | requery queue
 *
 * The wallet is debited before the provider call. A pending result is never
 * refunded blindly: requery keeps asking until the provider gives a final answer.
 */
class PurchaseService
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ProviderManager $providers,
        private readonly MeterVerifier $verifier,
    ) {}

    /** @return array{amount:int, fee:int, total:int, commission:int, agent_commission:int} */
    public function quote(User $user, Service $service, int $amount): array
    {
        $fee = $user->isAgent() ? 0 : $service->fee;
        $commission = $service->commissionOn($amount);
        $agentCommission = $user->isAgent() && $user->agentProfile
            ? (int) floor($commission * $user->agentProfile->share() / 100)
            : 0;

        return ['amount' => $amount, 'fee' => $fee, 'total' => $amount + $fee, 'commission' => $commission, 'agent_commission' => $agentCommission];
    }

    /**
     * @param  array{service_code:string, target:string, amount?:int, meter_type?:string, variation_code?:string,
     *               customer_phone?:string, idempotency_key:string, channel?:string, batch_id?:int}  $spec
     *
     * @throws PurchaseRejected
     */
    public function buy(User $user, array $spec): Transaction
    {
        $key = $spec['idempotency_key'];
        if ($existing = $user->transactions()->where('idempotency_key', $key)->first()) {
            Log::info('purchase.duplicate', ['ref' => $existing->reference, 'key' => $key]);

            return $existing;
        }

        $lock = Cache::lock("purchase:user:{$user->id}", 15);
        if (! $lock->block(5)) {
            throw new PurchaseRejected('Another purchase is still being processed. Please wait a moment.', 'in_flight', 409);
        }

        try {
            if ($existing = $user->transactions()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $tx = $this->createAndDebit($user, $spec);
        } finally {
            $lock->release();
        }

        return $this->send($tx);
    }

    private function createAndDebit(User $user, array $spec): Transaction
    {
        if ($user->status !== 'active') {
            throw new PurchaseRejected('This account is suspended. Contact support.', 'suspended', 403);
        }

        $service = Service::byCode($spec['service_code']);
        if (! $service || ! $service->active) {
            throw new PurchaseRejected('This service is temporarily unavailable. Please try again later.', 'service_off');
        }

        $details = $this->resolveDetails($service, $spec);
        $amount = $details['amount'];
        if ($amount < $service->min_amount || $amount > $service->max_amount) {
            throw new PurchaseRejected('Enter between '.Money::format($service->min_amount).' and '.Money::format($service->max_amount).'.', 'amount');
        }

        $q = $this->quote($user, $service, $amount);
        if ($user->spentToday() + $q['total'] > $user->dailyLimit()) {
            throw new PurchaseRejected('This goes over your daily limit of '.Money::format($user->dailyLimit()).'.', 'daily_limit');
        }

        try {
            return DB::transaction(function () use ($user, $service, $spec, $details, $q) {
                $tx = Transaction::create([
                    'reference' => References::transaction(),
                    'request_id' => References::vtpassRequestId(),
                    'idempotency_key' => $spec['idempotency_key'],
                    'user_id' => $user->id,
                    'batch_id' => $spec['batch_id'] ?? null,
                    'service_code' => $service->code,
                    'category' => $service->category,
                    'channel' => $spec['channel'] ?? 'app',
                    'target' => $spec['target'],
                    'meter_type' => $details['meter_type'],
                    'variation_code' => $details['variation_code'],
                    'variation_name' => $details['variation_name'],
                    'customer_name' => $details['customer_name'],
                    'customer_phone' => $spec['customer_phone'] ?? null,
                    'amount' => $q['amount'],
                    'fee' => $q['fee'],
                    'commission' => $q['commission'],
                    'status' => 'pending',
                    'provider' => $service->provider,
                ]);

                $this->ledger->debit($user, $q['total'], $tx->reference, 'purchase', [
                    'transaction_id' => $tx->id,
                    'description' => trim($service->name.' '.($details['variation_name'] ?? '')),
                ]);

                return $tx;
            });
        } catch (InsufficientFunds $e) {
            throw new PurchaseRejected($e->getMessage(), 'insufficient_funds');
        } catch (QueryException $e) {
            // Same idempotency key raced in from a second request.
            if ($existing = $user->transactions()->where('idempotency_key', $spec['idempotency_key'])->first()) {
                return $existing;
            }
            throw $e;
        }
    }

    /** Work out the trusted amount and labels; never take a data price from the client. */
    private function resolveDetails(Service $service, array $spec): array
    {
        $d = ['amount' => (int) ($spec['amount'] ?? 0), 'meter_type' => null, 'variation_code' => null, 'variation_name' => null, 'customer_name' => null];

        if ($service->category === 'electricity') {
            $type = $spec['meter_type'] ?? 'prepaid';
            $v = $this->verifier->verify($service, $spec['target'], $type);
            if (! $v->ok) {
                throw new PurchaseRejected($v->error ?? 'Meter not found.', 'meter_not_found');
            }
            if ($v->minAmount && $d['amount'] < $v->minAmount) {
                throw new PurchaseRejected('The minimum for this meter is '.Money::format($v->minAmount).'.', 'amount');
            }
            $d['meter_type'] = $type;
            $d['customer_name'] = $v->name;
        }

        if ($service->category === 'data') {
            $plan = collect($this->providers->get($service->provider)->dataPlans($service))
                ->firstWhere('code', $spec['variation_code'] ?? null);
            if (! $plan) {
                throw new PurchaseRejected('That data plan is no longer available. Pick another.', 'plan');
            }
            $d['amount'] = $plan['amount'];
            $d['variation_code'] = $plan['code'];
            $d['variation_name'] = $plan['name'];
        }

        return $d;
    }

    /** Send to the primary route, failing over only if the request provably never arrived. */
    public function send(Transaction $tx): Transaction
    {
        $tx->loadMissing('service', 'user');
        $result = $this->providers->get($tx->provider)->purchase($tx);
        Log::info('purchase.sent', ['ref' => $tx->reference, 'provider' => $tx->provider, 'status' => $result->status, 'msg' => $result->message]);

        $fallback = $tx->service->fallback_provider;
        if ($result->is(ProviderResult::UNREACHABLE) && $fallback && $fallback !== $tx->provider && $this->providers->configured($fallback)) {
            Log::warning('purchase.failover', ['ref' => $tx->reference, 'from' => $tx->provider, 'to' => $fallback]);
            $tx->forceFill(['provider' => $fallback])->save();
            $result = $this->providers->get($fallback)->purchase($tx);
        }

        return $this->apply($tx, $result);
    }

    /** Record a provider answer. Final states are never overwritten. */
    public function apply(Transaction $tx, ProviderResult $r): Transaction
    {
        $tx = DB::transaction(function () use ($tx, $r) {
            $tx = Transaction::whereKey($tx->id)->lockForUpdate()->first();
            if ($tx->isFinal()) {
                return $tx;
            }

            $tx->response_payload = $r->raw ?: ['status' => $r->status, 'message' => $r->message];
            if ($r->providerRef) {
                $tx->provider_ref = $r->providerRef;
            }

            switch ($r->status) {
                case ProviderResult::SUCCESS:
                    $this->markDelivered($tx, $r->token, $r->units);
                    break;

                case ProviderResult::FAILED:
                case ProviderResult::UNREACHABLE: // never reached any route: nothing was vended
                    $tx->save();
                    $tx = $this->ledger->refund($tx, $r->message ?? 'The provider declined the transaction.');
                    break;

                case ProviderResult::NOT_FOUND:
                    // A provider can lag a few seconds before it knows a request; give it three checks.
                    if ($tx->requery_count >= 3) {
                        $tx->save();
                        $tx = $this->ledger->refund($tx, 'The provider never received this request.');
                        break;
                    }
                    // no break
                default:
                    $tx->next_requery_at = now()->addSeconds($this->backoff($tx->requery_count));
                    $tx->save();
            }

            return $tx;
        });

        if ($tx->status === 'success' && $tx->wasChanged('status')) {
            $this->notifyDelivered($tx);
        }

        return $tx;
    }

    public function requery(Transaction $tx): Transaction
    {
        if ($tx->isFinal()) {
            return $tx;
        }
        $tx->loadMissing('service', 'user');
        $tx->increment('requery_count');
        $result = $this->providers->get($tx->provider)->requery($tx);
        Log::info('purchase.requery', ['ref' => $tx->reference, 'n' => $tx->requery_count, 'status' => $result->status]);
        $tx = $this->apply($tx, $result);

        if ($tx->status === 'pending') {
            $this->checkAge($tx);
        }

        return $tx;
    }

    private function checkAge(Transaction $tx): void
    {
        $age = $tx->created_at->diffInMinutes(now());
        if (! $tx->escalated_at && $age >= config('haske.requery.escalate_after_minutes')) {
            $tx->forceFill(['escalated_at' => now()])->save();
            Log::error('purchase.escalated', ['ref' => $tx->reference, 'minutes' => $age]);
            foreach (config('haske.alerts.phones') as $phone) {
                SendSms::dispatch($phone, "Haske ALERT: {$tx->reference} pending {$age} min on {$tx->provider}. Resolve in admin.");
            }
        }
        if ($age >= config('haske.requery.give_up_after_hours') * 60) {
            // Stop polling; it stays on the admin escalation list until a person resolves it.
            $tx->forceFill(['next_requery_at' => null])->save();
        }
    }

    private function backoff(int $attempt): int
    {
        $steps = config('haske.requery.backoff');

        return $steps[min($attempt, count($steps) - 1)];
    }

    private function markDelivered(Transaction $tx, ?string $token, ?string $units): void
    {
        $tx->fill([
            'status' => 'success',
            'token' => $token,
            'units' => $units,
            'next_requery_at' => null,
            'completed_at' => now(),
        ]);

        $user = $tx->user;
        if ($user->isAgent() && $user->agentProfile && $tx->commission > 0) {
            $tx->agent_commission = (int) floor($tx->commission * $user->agentProfile->share() / 100);
            if ($tx->agent_commission > 0) {
                $this->ledger->credit($user, $tx->agent_commission, $tx->reference.'-C', 'commission', [
                    'transaction_id' => $tx->id,
                    'description' => 'Agent commission',
                ]);
            }
        }
        $tx->save();
    }

    private function notifyDelivered(Transaction $tx): void
    {
        if (! $tx->token) {
            return;
        }
        $phone = $tx->customer_phone ?: $tx->user->phone;
        $short = $tx->service?->short_name ?? 'Electricity';
        $token = implode('-', str_split($tx->token, 4));
        $msg = $tx->user->locale === 'ha'
            ? "Haske: Token na {$short} {$token}. Unit {$tx->units}. Mita {$tx->target}. Lamba {$tx->reference}"
            : "Haske: {$short} token {$token}. Units {$tx->units}. Meter {$tx->target}. Ref {$tx->reference}";
        SendSms::dispatch($phone, $msg);
    }

    /** Admin: close an escalated purchase as delivered (e.g. token confirmed with the DisCo). */
    public function resolveAsDelivered(Transaction $tx, ?string $token): Transaction
    {
        return $this->apply($tx, ProviderResult::success($tx->provider_ref, $token ? preg_replace('/\D/', '', $token) : null, $tx->units));
    }

    /** Admin: close an escalated purchase as failed and refund it. */
    public function resolveAsFailed(Transaction $tx, string $reason): Transaction
    {
        return $this->ledger->refund($tx, $reason);
    }
}
