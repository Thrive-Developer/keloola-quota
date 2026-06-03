<?php

namespace Keloola\Quota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Keloola\Quota\Facades\Quota;
use Keloola\Quota\Models\QuotaMetric;

class QuotaCheckController extends Controller
{
    /**
     * Check quota details for a specific metric.
     */
    public function show(string $metricCode): JsonResponse
    {
        $metric = QuotaMetric::where('code', $metricCode)->first();

        if (!$metric) {
            return response()->json([
                'status' => 'error',
                'message' => 'Quota metric not found.',
            ], 404);
        }

        $limit = Quota::limit($metricCode);
        $used = Quota::used($metricCode);
        $remaining = Quota::remaining($metricCode);

        $mbUnits = ['mb', 'megabyte'];
        $unitLower = strtolower(trim($metric->unit ?? ''));

        if (in_array($unitLower, $mbUnits)) {
            $used = $used !== null ? round($used / 1048576, 2) : 0;
            $limit = $limit !== null ? round($limit / 1048576, 2) : null;
            $remaining = $remaining !== null ? round($remaining / 1048576, 2) : null;
        }

        return response()->json([
            'status' => 'ok',
            'data' => [
                'code'         => $metric->code,
                'name'         => $metric->name,
                'type'         => $metric->type,
                'unit'         => $metric->unit,
                'used'         => $used,
                'limit'        => $limit,
                'is_unlimited' => $limit === null,
                'remaining'    => $remaining,
                'percent'      => $limit ? round(($used / $limit) * 100, 1) : 0.0,
            ],
        ]);
    }
}
