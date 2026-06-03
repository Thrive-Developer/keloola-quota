<?php

namespace Keloola\Quota\Services;

use Illuminate\Http\Request;
use Keloola\Quota\Services\QuotaCheckService;

class StorageQuotaService
{
    /**
     * Check if the uploaded files exceed the remaining storage quota.
     *
     * @param Request $request
     * @param string $fileKey The key of the file in the request
     * @return bool Returns true if quota is sufficient or no file uploaded, false if exceeded
     */
    public static function hasSufficientQuota(Request $request, string $fileKey = 'attachments'): bool
    {
        if (!$request->hasFile($fileKey)) {
            return true;
        }

        $apiUrl = config('keloola-quota.storage.api_url', 'https://file.keloola.test');
        $metricCode = config('keloola-quota.storage.metric_code', 'storage_space');

        $response = app(QuotaCheckService::class)->checkQuota($apiUrl, $metricCode, $request->token);
        
        $files = $request->file($fileKey);
        $totalSize = 0;

        if (is_array($files)) {
            foreach ($files as $file) {
                $totalSize += $file->getSize();
            }
        } else {
            $totalSize = $files->getSize();
        }

        $totalSizeMb = $totalSize / 1048576; // Convert bytes to MB
        return $totalSizeMb <= data_get($response, 'data.remaining', 0);
    }
}
