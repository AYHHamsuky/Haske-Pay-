<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FundingEvent;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Services\Funding;
use App\Services\Providers\FakeProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

/** The HTML demo's "Behind the screen" panel, against the real system. Fake mode only. */
class SandboxController extends Controller
{
    public function __construct()
    {
        abort_unless(config('haske.drivers.bills') === 'fake', 404);
    }

    public function show()
    {
        $log = collect()
            ->merge(LedgerEntry::with('wallet.user:id,phone')->latest('id')->limit(25)->get()->map(fn ($e) => [
                'at' => $e->created_at->toIso8601String(), 'tag' => 'LEDGER', 'level' => $e->type === 'credit' ? 'ok' : '',
                'msg' => "{$e->type} ₦".number_format($e->amount / 100, 2)." ref={$e->reference} ({$e->reason}) → balance ₦".number_format($e->balance_after / 100, 2).' · '.$e->wallet->user->phone,
            ]))
            ->merge(Transaction::latest('updated_at')->limit(25)->get()->map(fn ($t) => [
                'at' => $t->updated_at->toIso8601String(), 'tag' => strtoupper($t->provider),
                'level' => ['success' => 'ok', 'pending' => 'warn', 'refunded' => 'bad', 'failed' => 'bad'][$t->status],
                'msg' => "{$t->reference} {$t->service_code} ₦".number_format($t->amount / 100)." → {$t->status}".($t->requery_count ? " after {$t->requery_count} requery" : '').($t->failure_reason ? " · {$t->failure_reason}" : ''),
            ]))
            ->merge(FundingEvent::latest('id')->limit(10)->get()->map(fn ($f) => [
                'at' => $f->created_at->toIso8601String(), 'tag' => 'WEBHOOK', 'level' => 'ok',
                'msg' => "Paystack charge.success ref={$f->reference} ₦".number_format($f->amount / 100)." → {$f->status}",
            ]))
            ->sortByDesc('at')->values()->take(60);

        return Inertia::render('Admin/Sandbox', [
            'outcome' => FakeProvider::nextOutcome(),
            'outcomes' => FakeProvider::OUTCOMES,
            'log' => $log,
            'lastFunding' => FundingEvent::where('status', 'credited')->latest('id')->first()?->only('reference', 'amount'),
        ]);
    }

    public function outcome(Request $request)
    {
        FakeProvider::setNextOutcome($request->validate(['outcome' => 'required|in:'.implode(',', FakeProvider::OUTCOMES)])['outcome']);

        return back()->with('success', 'Next purchase outcome: '.FakeProvider::nextOutcome());
    }

    public function replayWebhook(Funding $funding)
    {
        $last = FundingEvent::where('status', 'credited')->latest('id')->firstOrFail();
        $result = $funding->handlePaystackEvent($last->payload);

        return back()->with('success', "Replayed {$last->reference}: {$result}. Wallet unchanged.");
    }

    public function run(Request $request)
    {
        $cmd = $request->validate(['command' => 'required|in:haske:requery,haske:audit-ledger,haske:provider-balance'])['command'];
        Artisan::call($cmd, $cmd === 'haske:requery' ? ['--sync' => true] : []);

        return back()->with('success', trim(Artisan::output()) ?: 'Done.');
    }
}
