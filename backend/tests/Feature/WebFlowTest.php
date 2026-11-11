<?php

namespace Tests\Feature;

use App\Models\CheckoutOrder;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger;
use App\Services\Providers\FakeProvider;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebFlowTest extends TestCase
{
    public function test_guest_checkout_pays_then_vends_a_token(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Checkout/Index')->has('discos', 12));
        $this->get('/pay?c=airtime')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Checkout/Pay')->has('services.electricity')->where('category', 'airtime'));

        $this->post('/checkout', [
            'service_code' => 'kaduna-electric', 'target' => '04223456781', 'meter_type' => 'prepaid',
            'amount' => 3_000_00, 'customer_phone' => '08035550000',
        ])->assertRedirect();

        $order = CheckoutOrder::firstOrFail();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame(100_00, $order->fee);

        $this->post("/checkout/{$order->reference}/sandbox-pay")->assertRedirect("/checkout/{$order->reference}");

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $tx = Transaction::findOrFail($order->transaction_id);
        $this->assertSame('success', $tx->status);
        $this->assertSame('web', $tx->channel);
        $this->assertSame(0, User::where('phone', '08035550000')->first()->wallet->balance, 'payment fully spent on the token');

        // Paying twice (e.g. callback + webhook) never double-credits.
        $this->post("/checkout/{$order->reference}/sandbox-pay");
        $this->assertSame(1, Transaction::count());
    }

    public function test_failed_checkout_vend_leaves_money_in_buyers_wallet(): void
    {
        FakeProvider::setNextOutcome('failed');
        $this->post('/checkout', ['service_code' => 'mtn', 'target' => '08031234567', 'amount' => 500_00, 'customer_phone' => '08035550001']);
        $order = CheckoutOrder::firstOrFail();
        $this->post("/checkout/{$order->reference}/sandbox-pay");

        $this->assertSame(500_00, User::where('phone', '08035550001')->first()->wallet->balance);
    }

    public function test_sms_login_then_wallet_purchase_with_pin(): void
    {
        $this->post('/login/code', ['phone' => '08031234500'])->assertSessionHas('dev_code');
        $code = session('dev_code');
        $this->post('/login/verify', ['code' => $code])->assertRedirect('/app');

        $user = User::where('phone', '08031234500')->first();
        $user->setPin('2580');
        app(Ledger::class)->credit($user, 10_000_00, 'TEST-W', 'funding');
        $this->actingAs($user->fresh());

        $this->get('/app')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Wallet/Dashboard')->where('me.balance', 10_000_00));
        $this->post('/app/buy', [
            'service_code' => 'kaduna-electric', 'target' => '04223456781', 'meter_type' => 'prepaid',
            'amount' => 2_000_00, 'pin' => '2580', 'idempotency_key' => 'web-key-000001',
        ])->assertRedirect();
        $this->assertSame(10_000_00 - 2_100_00, $user->wallet->fresh()->balance);
    }

    public function test_admin_needs_two_factor_before_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/2fa');
        $this->actingAs($admin)->get('/admin/2fa')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/TwoFactor')->has('setup.secret'));
        $this->actingAs($admin)->withSession(['2fa_passed' => true])->get('/admin')->assertOk();
    }

    public function test_admin_manual_credit_is_ledgered_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->customer(0);

        $this->actingAs($admin)->withSession(['2fa_passed' => true])
            ->post("/admin/users/{$user->id}/adjust", ['type' => 'credit', 'amount' => 1500, 'reason' => 'Support refund HP-TEST'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1_500_00, $this->balance($user));
        $this->assertDatabaseHas('ledger_entries', ['reason' => 'manual_credit', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.manual_credit', 'actor_id' => $admin->id]);
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->customer();
        $this->buyElectricity($user);
        $tx = Transaction::first();
        $as = $this->actingAs($admin)->withSession(['2fa_passed' => true]);

        foreach (['/admin', '/admin/support', '/admin/transactions', "/admin/transactions/{$tx->id}", '/admin/users', "/admin/users/{$user->id}",
            '/admin/services', '/admin/reconciliation', '/admin/audit', '/admin/sandbox'] as $url) {
            $as->get($url)->assertOk();
        }
    }

    public function test_agent_bulk_vending(): void
    {
        $agent = $this->customer(50_000, 'agent');
        $agent->setPin('2580');

        $this->actingAs($agent)->post('/agent/bulk', [
            'service_code' => 'kaduna-electric', 'meter_type' => 'prepaid', 'pin' => '2580',
            'rows' => "04223456781, 2000, 08031234567\n04229876543,3000\n",
        ])->assertRedirect();

        $this->assertSame(2, Transaction::where('channel', 'bulk')->where('status', 'success')->count());
        $this->actingAs($agent)->get('/agent/commission')->assertOk();
    }

    public function test_customer_pages_render_and_history_filters_and_exports(): void
    {
        $user = $this->customer(20_000);
        $user->update(['name' => 'Aisha Bello']);
        $this->buyElectricity($user, 2_000);
        FakeProvider::setNextOutcome('failed');
        $this->buyElectricity($user, 3_000);
        $as = $this->actingAs($user->fresh());

        $as->get('/app')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Wallet/Dashboard')->where('stats.successful', 1)->where('stats.spent', 2_100_00));
        $as->get('/app/wallet?type=credit')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Wallet/Wallet')->where('filter', 'credit')->has('entries.data', 2));
        $as->get('/app/history?status=refunded')->assertOk()->assertInertia(fn (Assert $p) => $p->has('transactions.data', 1));
        $as->get('/app/profile')->assertOk();
        $csv = $as->get('/app/history.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Reference,Date,Service', $csv);
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1);
    }

    public function test_support_ticket_round_trip(): void
    {
        $user = $this->customer();
        $ref = $this->buyElectricity($user)->json('transaction.reference');

        $this->actingAs($user)->post('/app/help', ['reference' => 'HP-NOPE', 'type' => 'other', 'message' => 'Something odd happened'])->assertSessionHasErrors('reference');
        $this->actingAs($user)->post('/app/help', ['reference' => $ref, 'type' => 'token_rejected', 'message' => 'My meter says the token is invalid'])->assertSessionHasNoErrors();
        $ticket = SupportTicket::firstOrFail();
        $this->assertNotNull($ticket->transaction_id);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['2fa_passed' => true])
            ->patch("/admin/support/{$ticket->id}", ['reply' => 'Re-sent the token by SMS.'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->actingAs($user)->get('/app/help')->assertInertia(fn (Assert $p) => $p->where('tickets.0.status', 'resolved'));
    }

    public function test_agent_overview(): void
    {
        $agent = $this->customer(50_000, 'agent');
        $this->buyElectricity($agent, 10_000);

        $this->actingAs($agent->fresh())->get('/agent')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Agent/Overview')->where('report.today.sales', 1)->where('week.commission', 90_00)->has('rates', 3));
    }
}
