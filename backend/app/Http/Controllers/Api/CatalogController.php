<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Services\MeterVerifier;
use App\Services\Providers\ProviderManager;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class CatalogController extends Controller
{
    public function services(Request $request)
    {
        return response()->json(self::catalog($request->user()));
    }

    public static function catalog(?User $user): Collection
    {
        $agent = $user?->isAgent();

        return Service::orderBy('category')->orderBy('id')->get()->map(fn (Service $s) => [
            'code' => $s->code,
            'name' => $s->name,
            'short_name' => $s->short_name,
            'category' => $s->category,
            'network' => $s->meta['network'] ?? null,
            'active' => $s->active,
            'fee' => $agent ? 0 : $s->fee,
            'min_amount' => $s->min_amount,
            'max_amount' => $s->max_amount,
        ])->groupBy('category');
    }

    public function plans(string $code, ProviderManager $providers)
    {
        $service = Service::where('code', $code)->where('category', 'data')->firstOrFail();
        try {
            $plans = $providers->get($service->provider)->dataPlans($service);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not load plans. Try again.'], 503);
        }

        return response()->json($plans);
    }

    public function verifyMeter(Request $request, MeterVerifier $verifier)
    {
        $data = $request->validate([
            'service_code' => 'required|exists:services,code',
            'meter' => ['required', 'regex:/^(\d{11}|\d{13})$/'],
            'meter_type' => 'required|in:prepaid,postpaid',
        ], ['meter.regex' => 'Meter numbers have 11 or 13 digits.']);
        $service = Service::byCode($data['service_code']);
        if (! $service->active) {
            return response()->json(['ok' => false, 'error' => 'This company is temporarily unavailable.'], 422);
        }
        $result = $verifier->verify($service, $data['meter'], $data['meter_type']);

        return response()->json($result->toArray(), $result->ok ? 200 : 422);
    }

    public function detectNetwork(Request $request)
    {
        $phone = Phone::normalize($request->query('phone'));

        return response()->json(['phone' => $phone, 'network' => $phone ? Phone::network($phone) : null]);
    }
}
