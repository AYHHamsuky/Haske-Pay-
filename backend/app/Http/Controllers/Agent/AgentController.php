<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkBatch;
use App\Models\BulkBatch;
use App\Models\Service;
use App\Services\AgentReports;
use App\Services\PinGuard;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AgentController extends Controller
{
    public function overview(Request $request, AgentReports $reports)
    {
        $user = $request->user();
        $share = $user->agentProfile?->share() ?? 0;
        $rates = Service::active()->get()->groupBy('category')->map(function ($list, $cat) use ($share) {
            $min = $list->min('commission_rate') * $share / 100;
            $max = $list->max('commission_rate') * $share / 100;
            $fmt = fn ($r) => rtrim(rtrim(number_format($r, 2), '0'), '.').'%';

            return ['category' => $cat, 'rate' => $min === $max ? $fmt($min) : $fmt($min).'–'.$fmt($max), 'note' => $cat === 'electricity' ? 'Varies by DisCo' : 'Varies by network'];
        })->values();

        return Inertia::render('Agent/Overview', [
            'me' => MeController::present($user),
            'report' => $reports->summary($user, 1),
            'week' => $reports->summary($user, 7)['period'],
            'rates' => $rates,
            'recent' => $user->transactions()->with('service')->where('created_at', '>=', now()->startOfDay())->latest()->limit(8)->get()->map->toApi(),
        ]);
    }

    public function commission(Request $request, AgentReports $reports)
    {
        $days = (int) $request->query('days', 30);

        return Inertia::render('Agent/Commission', [
            'report' => $reports->summary($request->user(), $days),
            'services' => Service::pluck('name', 'code'),
        ]);
    }

    public function bulk(Request $request)
    {
        return Inertia::render('Agent/Bulk', [
            'discos' => Service::active()->where('category', 'electricity')->get(['code', 'name', 'short_name', 'min_amount', 'max_amount']),
            'batches' => BulkBatch::where('user_id', $request->user()->id)->latest()->limit(20)->get(),
            'balance' => $request->user()->walletOrCreate()->balance,
        ]);
    }

    /**
     * Rows are "meter,amount[,customer phone]" one per line, amount in naira.
     * Meters are vended one by one; each row's token SMS goes to its phone.
     */
    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'service_code' => 'required|exists:services,code',
            'meter_type' => 'required|in:prepaid,postpaid',
            'rows' => 'required|string|max:20000',
            'pin' => 'required|digits:4',
        ]);
        app(PinGuard::class)->verify($request->user(), $data['pin']);
        $service = Service::byCode($data['service_code']);

        $rows = [];
        $errors = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($data['rows'])) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv($line));
            $meter = preg_replace('/\D/', '', $cols[0] ?? '');
            $amount = Money::fromNaira($cols[1] ?? 0);
            $phone = isset($cols[2]) && $cols[2] !== '' ? Phone::normalize($cols[2]) : null;
            if (! preg_match('/^(\d{11}|\d{13})$/', $meter)) {
                $errors[] = 'Line '.($n + 1).': meter must be 11 or 13 digits.';
            } elseif ($amount < $service->min_amount || $amount > $service->max_amount) {
                $errors[] = 'Line '.($n + 1).': amount must be between '.Money::format($service->min_amount).' and '.Money::format($service->max_amount).'.';
            } elseif (isset($cols[2]) && $cols[2] !== '' && ! $phone) {
                $errors[] = 'Line '.($n + 1).': phone number is not valid.';
            } else {
                $rows[] = ['target' => $meter, 'amount' => $amount, 'phone' => $phone];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['rows' => implode(' ', array_slice($errors, 0, 5))]);
        }
        if (! $rows || count($rows) > 200) {
            throw ValidationException::withMessages(['rows' => 'Add between 1 and 200 meters.']);
        }
        $total = array_sum(array_column($rows, 'amount'));
        if ($total > $request->user()->wallet->balance) {
            throw ValidationException::withMessages(['rows' => 'This batch needs '.Money::format($total).'. Your wallet has '.Money::format($request->user()->wallet->balance).'.']);
        }

        $batch = BulkBatch::create(['user_id' => $request->user()->id, 'service_code' => $service->code, 'rows_total' => count($rows)]);
        ProcessBulkBatch::dispatch($batch->id, $rows, $data['meter_type']);

        return redirect()->route('agent.batch', $batch)->with('success', count($rows).' meters queued.');
    }

    public function batch(Request $request, BulkBatch $batch)
    {
        abort_unless($batch->user_id === $request->user()->id, 404);

        return Inertia::render('Agent/Batch', [
            'batch' => $batch,
            'service' => Service::byCode($batch->service_code)?->only('name', 'short_name'),
            'transactions' => $batch->transactions()->with('service')->orderBy('id')->get()->map->toApi(),
            'errors' => array_values(Cache::get("bulk.{$batch->id}.errors", [])),
        ]);
    }
}
