<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function index()
    {
        $since = now()->subDay();
        $stats = Transaction::where('created_at', '>=', $since)
            ->selectRaw("service_code, COUNT(*) as n, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as ok")
            ->groupBy('service_code')->get()->keyBy('service_code');

        return Inertia::render('Admin/Services', [
            'services' => Service::orderBy('category')->orderBy('id')->get()->map(fn (Service $s) => $s->toArray() + [
                'last_24h' => (int) ($stats[$s->code]->n ?? 0),
                'success_rate' => ($n = (int) ($stats[$s->code]->n ?? 0)) ? round($stats[$s->code]->ok / $n * 100) : null,
            ]),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $data = $request->validate([
            'active' => 'required|boolean',
            'provider' => 'required|in:vtpass,baxi',
            'fallback_provider' => 'nullable|in:vtpass,baxi|different:provider',
            'commission_rate' => 'required|numeric|min:0|max:20',
            'fee' => 'required|integer|min:0|max:100000', // kobo
            'min_amount' => 'required|integer|min:100',
            'max_amount' => 'required|integer|gt:min_amount',
        ]);
        $before = $service->only(array_keys($data));
        $service->update($data);
        AuditLog::record('service.updated', $service, ['before' => $before, 'after' => $data]);

        return back()->with('success', "{$service->name} saved.");
    }
}
