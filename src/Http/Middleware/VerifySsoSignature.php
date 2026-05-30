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
        $secret = config('keloola-quota.provisioning.secret');

        if (empty($secret)) {
            abort(500, 'Quota provisioning secret is not configured.');
        }

        $provided = $request->header('X-Quota-Signature', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            abort(401, 'Invalid provisioning signature.');
        }

        return $next($request);
    }
}
