<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestCode(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string', 'locale' => 'nullable|in:en,ha']);
        $phone = Phone::normalize($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number.']);
        $dev = $this->otp->send($phone, 'login', $data['locale'] ?? 'en');

        return response()->json(['sent' => true, 'phone' => $phone] + ($dev ? ['dev_code' => $dev] : []));
    }

    /**
     * Logging in binds the account to this device: tokens for other devices are revoked.
     */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|digits:6',
            'device_id' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:100',
        ]);
        $phone = Phone::normalize($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number.']);
        $user = $this->otp->login($phone, $data['code']);

        if ($user->device_id && $user->device_id !== $data['device_id']) {
            AuditLog::record('device.changed', $user, ['from' => $user->device_id, 'to' => $data['device_id']]);
        }
        $user->tokens()->delete();
        $user->forceFill(['device_id' => $data['device_id']])->save();
        $token = $user->createToken($data['device_id'], ['app'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'needs_pin' => ! $user->hasPin(),
            'needs_name' => ! $user->name,
            'user' => MeController::present($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }
}
