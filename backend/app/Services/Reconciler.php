<?php

namespace App\Services;

use App\Models\Reconciliation;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Compares a provider statement (CSV export from the VTpass dashboard) with our
 * transactions for the same day. Matching is on request_id, the id we send them.
 */
class Reconciler
{
    /**
     * @param  list<array{request_id:string, amount:int, status:string}>  $rows  amount in kobo
     */
    public function run(CarbonImmutable $date, array $rows, string $provider = 'vtpass', ?int $by = null): Reconciliation
    {
        $ours = Transaction::where('provider', $provider)
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->get()->keyBy('request_id');
        $theirs = collect($rows)->keyBy('request_id');

        $matched = 0;
        $issues = [];
        $difference = 0;

        foreach ($theirs as $rid => $row) {
            $tx = $ours->get($rid);
            $theyDelivered = in_array(strtolower($row['status']), ['delivered', 'success', 'successful'], true);
            if (! $tx) {
                // Includes requests made a few minutes before midnight and logged the next day.
                $tx = Transaction::where('request_id', $rid)->first();
                if (! $tx) {
                    $issues[] = ['type' => 'missing_ours', 'request_id' => $rid, 'their_amount' => $row['amount'], 'note' => 'On the statement but not in our system'];
                    $difference += $theyDelivered ? $row['amount'] : 0;

                    continue;
                }
            }
            $weDelivered = $tx->status === 'success';
            if ($tx->amount !== $row['amount'] || $theyDelivered !== $weDelivered) {
                $issues[] = [
                    'type' => 'mismatch', 'request_id' => $rid, 'reference' => $tx->reference,
                    'our_amount' => $tx->amount, 'their_amount' => $row['amount'],
                    'our_status' => $tx->status, 'their_status' => $row['status'],
                    'note' => $theyDelivered && ! $weDelivered
                        ? 'They charged us but we refunded the customer: confirm the token, then mark delivered or claim a reversal'
                        : 'Amounts or statuses differ',
                ];
                $difference += ($theyDelivered ? $row['amount'] : 0) - ($weDelivered ? $tx->amount : 0);

                continue;
            }
            $matched++;
        }

        foreach ($ours as $rid => $tx) {
            if (! $theirs->has($rid) && $tx->status === 'success') {
                $issues[] = ['type' => 'missing_theirs', 'request_id' => $rid, 'reference' => $tx->reference, 'our_amount' => $tx->amount, 'note' => 'We show delivered but it is not on their statement'];
                $difference -= $tx->amount;
            }
        }

        $count = fn ($t) => collect($issues)->where('type', $t)->count();

        return Reconciliation::create([
            'for_date' => $date->toDateString(),
            'provider' => $provider,
            'matched' => $matched,
            'missing_ours' => $count('missing_ours'),
            'missing_theirs' => $count('missing_theirs'),
            'mismatched' => $count('mismatch'),
            'difference' => $difference,
            'details' => $issues,
            'created_by' => $by,
        ]);
    }

    /**
     * Parse a statement CSV. Accepts VTpass export headers or our own template:
     * request_id, amount (naira), status.
     *
     * @return list<array{request_id:string, amount:int, status:string}>
     */
    public function parseCsv(string $contents): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', trim($contents))));
        if (! $lines) {
            return [];
        }
        $head = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), str_getcsv(array_shift($lines)));
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $head, true))->first(fn ($i) => $i !== false);
        $iRid = $col(['request_id', 'requestid', 'reference', 'request_reference']);
        $iAmt = $col(['amount', 'unit_price', 'total_amount']);
        $iSt = $col(['status', 'transaction_status']);
        if ($iRid === null || $iAmt === null || $iSt === null) {
            throw new \InvalidArgumentException('CSV needs request_id, amount and status columns.');
        }

        return collect($lines)->map(fn ($l) => str_getcsv($l))->filter(fn ($r) => isset($r[$iRid]) && $r[$iRid] !== '')
            ->map(fn ($r) => [
                'request_id' => trim($r[$iRid]),
                'amount' => Money::fromNaira(trim($r[$iAmt])),
                'status' => trim($r[$iSt]),
            ])->values()->all();
    }
}
