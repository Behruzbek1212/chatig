<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Hub-Signature-256 header on Instagram webhook POSTs:
 * sha256=HMAC_SHA256(raw_body, app_secret). Rejects forged payloads.
 */
class VerifyInstagramSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('chatig.instagram.app_secret');
        $header = (string) $request->header('X-Hub-Signature-256', '');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        if (! $secret || ! hash_equals($expected, $header)) {
            abort(403, 'Invalid signature.');
        }

        return $next($request);
    }
}
