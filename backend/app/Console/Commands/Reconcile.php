<?php

namespace App\Console\Commands;

use App\Services\Reconciler;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class Reconcile extends Command
{
    protected $signature = 'haske:reconcile {date : Statement day, YYYY-MM-DD} {csv : Path to the VTpass statement CSV} {--provider=vtpass}';

    protected $description = 'Match a provider statement against our transactions for a day';

    public function handle(Reconciler $reconciler): int
    {
        $rows = $reconciler->parseCsv(file_get_contents($this->argument('csv')));
        $r = $reconciler->run(CarbonImmutable::parse($this->argument('date')), $rows, $this->option('provider'));

        $this->table(['matched', 'missing ours', 'missing theirs', 'mismatched', 'difference'], [[
            $r->matched, $r->missing_ours, $r->missing_theirs, $r->mismatched, Money::format($r->difference),
        ]]);

        return $r->difference === 0 && ! $r->missing_ours && ! $r->missing_theirs && ! $r->mismatched ? self::SUCCESS : self::FAILURE;
    }
}
