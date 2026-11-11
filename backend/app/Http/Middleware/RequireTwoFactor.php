<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin pages need a TOTP check in this session, on top of the SMS login. */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('2fa_passed')) {
            return redirect()->route('2fa.show');
        }

        return $next($request);
    }
}
