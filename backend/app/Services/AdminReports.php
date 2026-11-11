<?php

namespace App\Services;

use App\Models\Service;
use App\Models\SystemCheck;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class AdminReports
{
    public function dashboard(int $days = 14): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $daily = Transaction::where('created_at', '>=', $from)
            ->selectRaw("DATE(created_at) as day,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN ('refunded','failed') THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as volume,
                SUM(CASE WHEN status = 'success' THEN commission + fee - agent_commission ELSE 0 END) as margin")
            ->groupBy(DB::raw('DATE(created_at)'))->get()->keyBy('day');

        $series = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $r = $daily->get($d->toDateString());
            $series[] = [
                'day' => $d->toDateString(),
                'delivered' => (int) ($r->delivered ?? 0),
                'failed' => (int) ($r->failed ?? 0),
                'pending' => (int) ($r->pending ?? 0),
                'volume' => (int) ($r->volume ?? 0),
                'margin' => (int) ($r->margin ?? 0),
            ];
        }
        $today = end($series);
        $attempted = $today['delivered'] + $today['failed'] + $today['pending'];

        $byCategory = Transaction::where('created_at', '>=', now()->startOfDay())->where('status', 'success')
            ->selectRaw('category, COUNT(*) as n, SUM(amount) as volume, SUM(commission + fee - agent_commission) as margin')
            ->groupBy('category')->get()
            ->map(fn ($r) => ['category' => $r->category, 'n' => (int) $r->n, 'volume' => (int) $r->volume, 'margin' => (int) $r->margin]);

        $failures = Transaction::where('created_at', '>=', now()->subDay())->whereIn('status', ['refunded', 'failed'])
            ->selectRaw('service_code, provider, COUNT(*) as n')->groupBy('service_code', 'provider')->orderByDesc('n')->limit(6)->get();

        return [
            'today' => $today + ['failure_rate' => $attempted ? round($today['failed'] / $attempted * 100, 1) : 0],
            'series' => $series,
            'by_category' => $byCategory,
            'top_failures' => $failures,
            'pending_count' => Transaction::where('status', 'pending')->count(),
            'escalated_count' => Transaction::where('status', 'pending')->whereNotNull('escalated_at')->count(),
            'oldest_pending_minutes' => ($o = Transaction::where('status', 'pending')->oldest()->value('created_at')) ? (int) now()->diffInMinutes($o, true) : null,
            'wallets_total' => (int) Wallet::sum('balance'),
            'services_off' => Service::where('active', false)->pluck('name'),
            'ledger_check' => SystemCheck::latestFor('ledger'),
            'provider_balance' => SystemCheck::latestFor('provider_balance'),
        ];
    }
}
