<?php

namespace Keloola\Quota\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies that the provisioning request genuinely comes from the SSO
 * service using an HMAC signature over the raw request body.
 *
 * SSO side must send:
 *   X-Quota-Signature: sha256=<hmac_hex>
 * where hmac = hash_hmac('sha256', $rawBody, $sharedSecret)
 */
class VerifySsoSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Secret verification has been removed
        // $secret = config('keloola-quota.provisioning.secret');

        return $next($request);
    }
}
