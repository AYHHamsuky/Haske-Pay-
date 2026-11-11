<?php

namespace App\Services\Providers;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

trait HandlesHttpErrors
{
    /**
     * Decide whether a network failure means the request never left (safe to fail over)
     * or that we lost the response (must requery before doing anything else).
     */
    protected function classifyNetworkError(Throwable $e): ProviderResult
    {
        $prev = $e instanceof ConnectionException ? $e->getPrevious() : $e;
        $errno = $prev instanceof ConnectException ? ($prev->getHandlerContext()['errno'] ?? null) : null;
        $msg = $e->getMessage();

        // 6 = could not resolve host, 7 = could not connect, 35 = TLS handshake failed.
        $neverSent = in_array($errno, [6, 7, 35], true)
            || str_contains($msg, 'Could not resolve host')
            || str_contains($msg, 'Failed to connect')
            || str_contains($msg, 'Connection refused')
            || str_contains($msg, 'Resolving timed out')
            || (str_contains($msg, 'Connection timed out') && ! str_contains($msg, 'bytes received'));

        return $neverSent
            ? ProviderResult::unreachable($this->name().' unreachable: '.$msg)
            : ProviderResult::pending($this->name().' response lost: '.$msg);
    }
}
