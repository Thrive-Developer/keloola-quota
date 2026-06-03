<?php

namespace Keloola\Quota\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuotaCheckService
{
    /**
     * Memanggil endpoint /api/quota/check/{metricCode} pada target API (satellite app)
     * untuk mengecek status pemakaian kuota secara remote (cross app).
     *
     * @param string $apiUrl Base URL dari target aplikasi (contoh: https://app.keloola.com)
     * @param string $metricCode Kode metrik kuota (contoh: 'users', 'storage_mb')
     * @param string $token Token JWT atau token lain untuk autentikasi ke target aplikasi
     * @param string $prefix Path prefix dari endpoint quota (default: 'api/quota')
     * @return array|null Mengembalikan data JSON quota atau null jika gagal
     */
    public function checkQuota(string $apiUrl, string $metricCode, string $token, string $prefix = 'api/quota'): ?array
    {
        if (empty($apiUrl)) {
            Log::warning('QuotaCheckService: API URL is empty.');
            return null;
        }

        $endpoint = rtrim($apiUrl, '/') . '/' . trim($prefix, '/') . "/check/{$metricCode}";

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(15)->get($endpoint);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('QuotaCheckService: Failed to check quota', [
                'api_url' => $apiUrl,
                'metric'  => $metricCode,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('QuotaCheckService: Exception occurred', [
                'api_url' => $apiUrl,
                'metric'  => $metricCode,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }
}
