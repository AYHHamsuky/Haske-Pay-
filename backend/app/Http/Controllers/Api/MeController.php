<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Payments\Paystack;
use App\Services\PinGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class MeController extends Controller
{
    public static function present(User $user): array
    {
        $user->loadMissing('wallet', 'virtualAccount', 'agentProfile');
        $va = $user->virtualAccount;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role,
            'locale' => $user->locale,
            'has_pin' => $user->hasPin(),
            'balance' => $user->wallet?->balance ?? 0,
            'virtual_account' => $va ? ['bank' => $va->bank, 'account_number' => $va->account_number, 'account_name' => $va->account_name] : null,
            'agent' => $user->agentProfile ? [
                'business_name' => $user->agentProfile->business_name,
                'tier' => $user->agentProfile->commission_tier,
                'share' => $user->agentProfile->share(),
            ] : null,
            'daily_limit' => $user->dailyLimit(),
            'spent_today' => $user->spentToday(),
            'support_whatsapp' => config('haske.support_whatsapp'),
        ];
    }

    public function show(Request $request, Paystack $paystack)
    {
        $user = $request->user();
        if (! $user->virtualAccount && $user->name) {
            try {
                $paystack->ensureVirtualAccount($user);
                $user->load('virtualAccount');
            } catch (Throwable $e) {
                report($e); // the app shows "account number coming soon" and retries later
            }
        }

        return response()->json(self::present($user));
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'sometimes|string|min:3|max:80', 'locale' => 'sometimes|in:en,ha']);
        $request->user()->update($data);

        return $this->show($request, app(Paystack::class));
    }

    /** First PIN needs nothing extra; changing it needs the old PIN or a fresh SMS code. */
    public function setPin(Request $request, PinGuard $guard, OtpService $otp)
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4', 'not_in:0000,1111,1234,4321,2222,9999'],
            'current_pin' => 'nullable|digits:4',
            'code' => 'nullable|digits:6',
        ], ['pin.not_in' => 'Choose a PIN that is harder to guess.']);
        $user = $request->user();

        if ($user->hasPin()) {
            if (! empty($data['code'])) {
                if (! $otp->check($user->phone, $data['code'])) {
                    throw ValidationException::withMessages(['code' => 'That code is wrong or has expired.']);
                }
            } else {
                $guard->verify($user, $data['current_pin'] ?? null);
            }
        }
        $user->setPin($data['pin']);

        return response()->json(['ok' => true]);
    }
}
