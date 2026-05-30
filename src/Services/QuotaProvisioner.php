<?php

namespace Keloola\Quota\Services;

use Illuminate\Support\Facades\DB;
use Keloola\Quota\Models\AppPlanQuota;
use Keloola\Quota\Models\QuotaMetric;

/**
 * Receives quota definitions pushed by the SSO/billing service when an
 * organization installs an app, or when a plan/quota changes upstream.
 *
 * The operation is idempotent (upsert), so the same payload can be sent
 * on install AND on every subsequent change without creating duplicates.
 */
class QuotaProvisioner
{
    /**
     * Provision (or re-sync) quota definitions for a single plan.
     *
     * Expected payload shape:
     * [
     *   'app_id'      => 2,
     *   'app_plan_id' => 202,
     *   'metrics'     => [
     *     [
     *       'name'  => 'Storage Space',
     *       'code'  => 'storage_mb',
     *       'type'  => 'snapshot',          // snapshot | counter
     *       'unit'  => 'MB',
     *       'limit' => 102400,
     *       'is_unlimited' => false,
     *     ],
     *     ...
     *   ],
     * ]
     *
     * @return array{metrics: int, limits: int}
     */
    public function provision(array $payload): array
    {
        $appId  = $payload['app_id'];
        $planId = $payload['app_plan_id'];
        $metrics = $payload['metrics'] ?? [];

        return DB::transaction(function () use ($appId, $planId, $metrics) {
            $metricCount = 0;
            $limitCount  = 0;

            foreach ($metrics as $m) {
                // 1. Upsert the metric definition for this app.
                $metric = QuotaMetric::updateOrCreate(
                    ['app_id' => $appId, 'code' => $m['code']],
                    [
                        'name'      => $m['name'],
                        'type'      => $m['type'],
                        'unit'      => $m['unit'] ?? null,
                        'is_active' => $m['is_active'] ?? true,
                    ]
                );
                $metricCount++;

                // 2. Upsert the per-plan limit.
                AppPlanQuota::updateOrCreate(
                    ['app_plan_id' => $planId, 'quota_metric_id' => $metric->id],
                    [
                        'limit'           => $m['limit'] ?? 0,
                        'is_unlimited'    => $m['is_unlimited'] ?? false,
                    ]
                );
                $limitCount++;
            }

            return ['metrics' => $metricCount, 'limits' => $limitCount];
        });
    }

    /**
     * Remove a plan's quota definitions (e.g. org uninstalls the app or
     * the plan is deleted upstream). Metrics are kept; only the plan limits
     * are removed since metrics may be shared across plans.
     */
    public function deprovisionPlan(int|string $appPlanId): int
    {
        return AppPlanQuota::where('app_plan_id', $appPlanId)->delete();
    }
}
