<?php

namespace Keloola\Quota\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SsoClient
{
    /**
     * Get user profile from SSO using the provided Bearer token.
     * We cache the response briefly to avoid spamming the SSO server.
     */
    public function getUserProfile(string $token): ?array
    {
        $cacheKey = 'keloola_quota_user_' . md5($token);

        return Cache::remember($cacheKey, 60, function () use ($token) {
            $ssoUrl = config('keloola-quota.sso.base_url', 'http://localhost:8000') . '/api/jwt/user';

            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->post($ssoUrl, [
                        'token' => $token
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    return $json['data'] ?? null;
                }
            } catch (\Exception $e) {
                // Return null on failure
            }

            return null;
        });
    }
}
