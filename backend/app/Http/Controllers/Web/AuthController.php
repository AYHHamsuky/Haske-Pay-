<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(Request $request)
    {
        if ($request->boolean('change')) {
            $request->session()->forget('login_phone');
        }

        return Inertia::render('Auth/Login', ['phone' => $request->session()->get('login_phone')]);
    }

    public function send(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string']);
        $phone = Phone::normalize($data['phone']) ?? throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number.']);
        $dev = $this->otp->send($phone);
        $request->session()->put('login_phone', $phone);

        return back()->with('dev_code', $dev)->with('success', 'We sent a 6-digit code to '.$phone.'.');
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $phone = $request->session()->get('login_phone') ?? throw ValidationException::withMessages(['code' => 'Start again: enter your phone number.']);
        $user = $this->otp->login($phone, $data['code']);

        Auth::login($user, remember: ! $user->isAdmin());
        $request->session()->regenerate();
        $request->session()->forget('login_phone');
        AuditLog::record('login.web', $user);

        return $user->isAdmin() ? redirect()->route('2fa.show') : redirect()->intended('/app');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
