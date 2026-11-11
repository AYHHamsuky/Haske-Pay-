<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** App tokens are named after the device they were issued to; reject them anywhere else. */
class EnsureBoundDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'getAttribute') && $token->name !== $request->header('X-Device-Id')) {
            return response()->json(['message' => 'This session belongs to another device. Log in again.', 'code' => 'device_mismatch'], 401);
        }

        return $next($request);
    }
}
