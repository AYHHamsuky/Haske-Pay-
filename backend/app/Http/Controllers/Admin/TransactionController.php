<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only('status', 'category', 'provider', 'q', 'escalated', 'channel');
        $q = Transaction::with('service', 'user:id,name,phone,role')->latest();
        foreach (['status', 'category', 'provider', 'channel'] as $k) {
            if (! empty($f[$k])) {
                $q->where($k, $f[$k]);
            }
        }
        if (! empty($f['escalated'])) {
            $q->where('status', 'pending')->whereNotNull('escalated_at');
        }
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('target', 'like', "%{$term}%")
                ->orWhere('request_id', 'like', "%{$term}%")->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$term}%")));
        }

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $q->paginate(30)->withQueryString()->through(fn (Transaction $t) => $t->toApi() + [
                'user' => $t->user?->only('id', 'name', 'phone', 'role'),
                'channel' => $t->channel,
                'escalated' => (bool) $t->escalated_at,
                'margin' => $t->margin(),
            ]),
            'filters' => $f,
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('service', 'user', 'ledgerEntries');

        return Inertia::render('Admin/Transactions/Show', [
            'tx' => $transaction->toApi() + [
                'request_id' => $transaction->request_id,
                'provider_ref' => $transaction->provider_ref,
                'channel' => $transaction->channel,
                'commission' => $transaction->commission,
                'margin' => $transaction->margin(),
                'escalated_at' => $transaction->escalated_at?->toIso8601String(),
                'next_requery_at' => $transaction->next_requery_at?->toIso8601String(),
                'customer_phone' => $transaction->customer_phone,
                'request_payload' => $transaction->request_payload,
                'response_payload' => $transaction->response_payload,
                'user' => $transaction->user->only('id', 'name', 'phone', 'role'),
            ],
            'entries' => $transaction->ledgerEntries()->orderBy('id')->get(),
            'audit' => AuditLog::with('actor:id,name,phone')->where('subject_type', $transaction->getMorphClass())->where('subject_id', $transaction->id)->latest('id')->get(),
        ]);
    }

    public function requery(Transaction $transaction, PurchaseService $purchases)
    {
        $tx = $purchases->requery($transaction);
        AuditLog::record('transaction.requery', $tx, ['result' => $tx->status]);

        return back()->with('success', "Requeried: {$tx->status}.");
    }

    /** Close an escalated purchase after checking with the provider by phone or dashboard. */
    public function resolve(Request $request, Transaction $transaction, PurchaseService $purchases)
    {
        $data = $request->validate([
            'outcome' => 'required|in:delivered,refund',
            'token' => 'nullable|string|max:40',
            'reason' => 'required|string|min:5|max:200',
        ]);
        abort_if($transaction->isFinal(), 422, 'This transaction is already final.');

        $tx = $data['outcome'] === 'delivered'
            ? $purchases->resolveAsDelivered($transaction, $data['token'] ?? null)
            : $purchases->resolveAsFailed($transaction, $data['reason']);
        AuditLog::record('transaction.resolved', $tx, $data);

        return back()->with('success', "{$tx->reference} marked {$tx->status}.");
    }
}
