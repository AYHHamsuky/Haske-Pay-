<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Ledger;
use App\Services\Providers\FakeProvider;
use App\Services\PurchaseService;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    public function test_successful_token_debits_wallet_once_and_returns_token(): void
    {
        $user = $this->customer(20_000);

        $res = $this->buyElectricity($user, 5_000)->assertCreated();

        $res->assertJsonPath('transaction.status', 'success');
        $this->assertMatchesRegularExpression('/^\d{20}$/', $res->json('transaction.token'));
        $this->assertSame(20_000_00 - 5_100_00, $this->balance($user)); // ₦100 fee
        $this->assertSame(1, LedgerEntry::where('reason', 'purchase')->count());
        $this->assertDatabaseHas('saved_meters', ['user_id' => $user->id, 'meter_number' => '04223456781']);
    }

    public function test_failed_purchase_is_refunded_and_ledger_shows_both_entries(): void
    {
        FakeProvider::setNextOutcome('failed');
        $user = $this->customer(20_000);

        $res = $this->buyElectricity($user, 5_000)->assertCreated();

        $res->assertJsonPath('transaction.status', 'refunded');
        $this->assertSame(20_000_00, $this->balance($user));
        $ref = $res->json('transaction.reference');
        $this->assertDatabaseHas('ledger_entries', ['reference' => $ref, 'type' => 'debit']);
        $this->assertDatabaseHas('ledger_entries', ['reference' => $ref.'-R', 'type' => 'credit']);
    }

    public function test_pending_purchase_is_resolved_by_requery_not_refunded(): void
    {
        FakeProvider::setNextOutcome('pending');
        $user = $this->customer(20_000);

        $ref = $this->buyElectricity($user, 5_000)->assertJsonPath('transaction.status', 'pending')->json('transaction.reference');
        $tx = Transaction::where('reference', $ref)->first();
        $this->assertNotNull($tx->next_requery_at);
        $this->assertSame(20_000_00 - 5_100_00, $this->balance($user), 'money stays debited while pending');

        $svc = app(PurchaseService::class);
        $this->assertSame('pending', $svc->requery($tx)->status);
        $done = $svc->requery($tx->fresh());

        $this->assertSame('success', $done->status);
        $this->assertNotNull($done->token);
        $this->assertSame(2, $done->requery_count);
    }

    public function test_requery_command_picks_up_due_transactions(): void
    {
        FakeProvider::setNextOutcome('pending');
        $user = $this->customer();
        $ref = $this->buyElectricity($user)->json('transaction.reference');
        Transaction::where('reference', $ref)->update(['next_requery_at' => now()->subSecond()]);

        $this->artisan('haske:requery')->assertSuccessful();
        Transaction::where('reference', $ref)->update(['next_requery_at' => now()->subSecond()]);
        $this->artisan('haske:requery')->assertSuccessful();

        $this->assertSame('success', Transaction::where('reference', $ref)->value('status'));
    }

    public function test_vtpass_down_fails_over_to_baxi_with_the_same_request_id(): void
    {
        FakeProvider::setNextOutcome('down');
        $user = $this->customer();

        $res = $this->buyElectricity($user)->assertCreated();

        $res->assertJsonPath('transaction.status', 'success')->assertJsonPath('transaction.provider', 'baxi');
        $this->assertSame(1, LedgerEntry::where('reason', 'purchase')->count(), 'no double debit on failover');
    }

    public function test_double_tap_with_same_idempotency_key_creates_one_purchase(): void
    {
        $user = $this->customer(20_000);
        $extra = ['idempotency_key' => 'tap-key-123456'];

        $a = $this->buyElectricity($user, 2_000, $extra)->json('transaction.reference');
        $b = $this->buyElectricity($user, 2_000, $extra)->json('transaction.reference');

        $this->assertSame($a, $b);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(20_000_00 - 2_100_00, $this->balance($user));
    }

    public function test_insufficient_funds_is_rejected_before_any_money_moves(): void
    {
        $user = $this->customer(1_000);

        $this->buyElectricity($user, 5_000)->assertStatus(422)->assertJsonPath('code', 'insufficient_funds');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(1_000_00, $this->balance($user));
    }

    public function test_daily_limit_is_enforced(): void
    {
        $user = $this->customer(400_000);
        $this->buyElectricity($user, 150_000)->assertCreated();

        $this->buyElectricity($user, 60_000)->assertStatus(422)->assertJsonPath('code', 'daily_limit');
    }

    public function test_disabled_service_rejects_purchases(): void
    {
        Service::where('code', 'kaduna-electric')->update(['active' => false]);
        $user = $this->customer();

        $this->buyElectricity($user)->assertStatus(422)->assertJsonPath('code', 'service_off');
    }

    public function test_unknown_meter_is_rejected(): void
    {
        $user = $this->customer();

        $this->buyElectricity($user, 5_000, ['target' => '00000000000'])->assertStatus(422)->assertJsonPath('code', 'meter_not_found');
    }

    public function test_agent_pays_no_fee_and_earns_commission(): void
    {
        $agent = $this->customer(50_000, 'agent'); // silver tier keeps 60% of commission

        $res = $this->buyElectricity($agent, 10_000)->assertCreated();

        // KAEDCO pays 1.5% = ₦150; agent keeps 60% = ₦90.
        $res->assertJsonPath('transaction.fee', 0)->assertJsonPath('transaction.agent_commission', 90_00);
        $this->assertSame(50_000_00 - 10_000_00 + 90_00, $this->balance($agent));
    }

    public function test_data_price_comes_from_the_plan_not_the_client(): void
    {
        $user = $this->customer();

        $res = $this->actingAsDevice($user)->postJson('/api/v1/purchases', [
            'service_code' => 'mtn-data', 'target' => '08031234567', 'variation_code' => 'mtn-data-1',
            'amount' => 100, 'pin' => '2580', 'idempotency_key' => 'data-key-0001',
        ])->assertCreated();

        $res->assertJsonPath('transaction.amount', 1_000_00);
    }

    public function test_airtime_purchase(): void
    {
        $user = $this->customer();

        $this->actingAsDevice($user)->postJson('/api/v1/purchases', [
            'service_code' => 'mtn', 'target' => '+2348031234567', 'amount' => 500_00, 'pin' => '2580', 'idempotency_key' => 'air-key-0001',
        ])->assertCreated()->assertJsonPath('transaction.target', '08031234567')->assertJsonPath('transaction.status', 'success');
    }

    public function test_refund_is_idempotent_and_delivered_purchases_cannot_be_refunded(): void
    {
        FakeProvider::setNextOutcome('pending');
        $user = $this->customer(20_000);
        $tx = Transaction::where('reference', $this->buyElectricity($user)->json('transaction.reference'))->first();
        $ledger = app(Ledger::class);

        $ledger->refund($tx, 'test');
        $ledger->refund($tx->fresh(), 'test again');
        $this->assertSame(20_000_00, $this->balance($user));

        $ok = Transaction::where('reference', $this->buyElectricity($user)->json('transaction.reference'))->first();
        FakeProvider::setNextOutcome('success');
        $this->expectException(\InvalidArgumentException::class);
        $ledger->refund(app(PurchaseService::class)->resolveAsDelivered($ok, '12345678901234567890'), 'nope');
    }

    public function test_ledger_audit_passes_after_mixed_activity(): void
    {
        $user = $this->customer(50_000);
        foreach (['success', 'failed', 'down', 'pending'] as $o) {
            FakeProvider::setNextOutcome($o);
            $this->buyElectricity($user, 2_000);
        }

        $this->assertSame([], app(Ledger::class)->audit());
        $this->artisan('haske:audit-ledger')->assertSuccessful();
    }
}
