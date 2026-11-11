<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AgentReports
{
    public function summary(User $agent, int $days = 14): array
    {
        $days = max(1, min($days, 90));
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Transaction::where('user_id', $agent->id)
            ->where('status', 'success')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as sales, SUM(amount) as volume, SUM(agent_commission) as commission')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get()->keyBy('day');

        $series = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $k = $d->toDateString();
            $r = $rows->get($k);
            $series[] = ['day' => $k, 'sales' => (int) ($r->sales ?? 0), 'volume' => (int) ($r->volume ?? 0), 'commission' => (int) ($r->commission ?? 0)];
        }
        $today = end($series);

        $byService = Transaction::where('user_id', $agent->id)->where('status', 'success')->where('created_at', '>=', $from)
            ->selectRaw('service_code, COUNT(*) as sales, SUM(amount) as volume, SUM(agent_commission) as commission')
            ->groupBy('service_code')->orderByDesc('volume')->get()
            ->map(fn ($r) => ['service_code' => $r->service_code, 'sales' => (int) $r->sales, 'volume' => (int) $r->volume, 'commission' => (int) $r->commission]);

        return [
            'today' => $today,
            'period' => [
                'days' => $days,
                'sales' => array_sum(array_column($series, 'sales')),
                'volume' => array_sum(array_column($series, 'volume')),
                'commission' => array_sum(array_column($series, 'commission')),
            ],
            'series' => $series,
            'by_service' => $byService,
            'tier' => $agent->agentProfile?->commission_tier,
            'share' => $agent->agentProfile?->share(),
        ];
    }
}
