<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\Reconciler;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ReconcilerTest extends TestCase
{
    public function test_statement_matches_and_flags_differences(): void
    {
        $user = $this->customer(50_000);
        $a = Transaction::where('reference', $this->buyElectricity($user, 2_000)->json('transaction.reference'))->first();
        $b = Transaction::where('reference', $this->buyElectricity($user, 3_000)->json('transaction.reference'))->first();

        $csv = "request_id,amount,status\n{$a->request_id},2000,delivered\n{$b->request_id},3500,delivered\nGHOST123456789,1000,delivered\n";
        $r = new Reconciler;
        $result = $r->run(CarbonImmutable::today(), $r->parseCsv($csv));

        $this->assertSame(1, $result->matched);
        $this->assertSame(1, $result->mismatched);
        $this->assertSame(1, $result->missing_ours);
        $this->assertSame(500_00 + 1_000_00, $result->difference);
    }
}
