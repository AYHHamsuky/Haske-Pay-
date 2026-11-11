<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Reconciliation;
use App\Services\Reconciler;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class ReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $selected = $request->query('id') ? Reconciliation::find($request->query('id')) : Reconciliation::latest('id')->first();

        return Inertia::render('Admin/Reconciliation', [
            'runs' => Reconciliation::latest('id')->limit(30)->get()->makeHidden('details'),
            'selected' => $selected,
        ]);
    }

    public function store(Request $request, Reconciler $reconciler)
    {
        $data = $request->validate([
            'for_date' => 'required|date|before_or_equal:today',
            'provider' => 'required|in:vtpass,baxi',
            'statement' => 'required|file|mimes:csv,txt|max:5120',
        ]);
        try {
            $rows = $reconciler->parseCsv($request->file('statement')->get());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['statement' => $e->getMessage()]);
        }
        $run = $reconciler->run(CarbonImmutable::parse($data['for_date']), $rows, $data['provider'], $request->user()->id);
        AuditLog::record('reconciliation.run', $run, ['rows' => count($rows)]);

        return redirect()->route('admin.reconciliation', ['id' => $run->id]);
    }
}
