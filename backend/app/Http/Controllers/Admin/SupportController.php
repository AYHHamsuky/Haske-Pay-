<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendSms;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        return Inertia::render('Admin/Support', [
            'tickets' => SupportTicket::with('user:id,name,phone', 'transaction:id,reference,status,amount,fee')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'types' => SupportTicket::TYPES,
            'status' => $status,
        ]);
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['reply' => 'required|string|min:5|max:1000', 'notify' => 'boolean']);
        $ticket->update(['reply' => $data['reply'], 'status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        if ($data['notify'] ?? true) {
            SendSms::dispatch($ticket->user->phone, "Haske support (SUP-{$ticket->id}): {$data['reply']}");
        }
        AuditLog::record('support.resolved', $ticket, ['reply' => $data['reply']]);

        return back()->with('success', "SUP-{$ticket->id} resolved.");
    }
}
