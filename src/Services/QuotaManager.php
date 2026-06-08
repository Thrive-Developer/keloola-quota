<?php

namespace Keloola\Quota\Services;

use Illuminate\Support\Facades\DB;
use Keloola\Quota\Exceptions\QuotaExceededException;
use Keloola\Quota\Exceptions\QuotaMetricNotFoundException;
use Keloola\Quota\Models\AppPlanQuota;
use Keloola\Quota\Models\QuotaMetric;
use Keloola\Quota\Models\QuotaUsage;
use Keloola\Quota\Models\QuotaUsageLog;

class QuotaManager
{
    protected ?string $appId = null;
    protected ?string $organizationId = null;
    protected ?string $appPlanId = null;

    /**
     * Scope the manager to an app.
     */
    public function app(string|int $appId): static
    {
        $this->appId = (string) $appId;
        return $this;
    }

    /**
     * Scope the manager to an organization (the tenant consuming quota).
     */
    public function for(string|int $organizationId): static
    {
        $this->organizationId = (string) $organizationId;
        return $this;
    }

    /**
     * Set the active plan whose limits should be enforced.
     */
    public function plan(string|int $appPlanId): static
    {
        $this->appPlanId = (string) $appPlanId;
        return $this;
    }

    /* ----------------------------------------------------------------------
     | Reading
     |----------------------------------------------------------------------*/

    /**
     * The limit for a metric on the active plan. Returns null if unlimited.
     */
    public function limit(string $code): ?int
    {
        $metric = $this->metric($code);
        if (! $metric) {
            return null;
        }

        $planQuota = $this->planQuota($metric->id);

        if (! $planQuota || $planQuota->is_unlimited) {
            return null; // unlimited
        }

        return $planQuota->limit;
    }

    /**
     * Whether the metric is explicitly flagged unlimited on the active plan.
     *
     * Reads the `is_unlimited` column on app_plan_quotas directly, so an
     * un-provisioned metric (no plan quota row) is NOT treated as unlimited.
     */
    public function isUnlimited(string $code): bool
    {
        $metric = $this->metric($code);
        if (! $metric) {
            return true;
        }

        return (bool) $this->planQuota($metric->id)?->is_unlimited;
    }

    /**
     * Current usage of a metric for the active organization.
     */
    public function used(string $code): int
    {
        $metric = $this->metric($code);
        if (! $metric) {
            return 0;
        }

        $usage  = $this->usageRow($metric, create: false);

        return $usage?->used ?? 0;
    }

    /**
     * Remaining quota. Returns null when unlimited.
     */
    public function remaining(string $code): ?int
    {
        $limit = $this->limit($code);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->used($code));
    }

    /**
     * Whether adding $amount would stay within the limit.
     */
    public function canConsume(string $code, int $amount = 1): bool
    {
        $limit = $this->limit($code);
        if ($limit === null) {
            return true; // unlimited
        }

        return ($this->used($code) + $amount) <= $limit;
    }

    public function hasReached(string $code): bool
    {
        return ! $this->canConsume($code, 0) || $this->remaining($code) === 0;
    }

    /* ----------------------------------------------------------------------
     | Writing
     |----------------------------------------------------------------------*/

    /**
     * Increment usage. Used by both snapshot (e.g. a user added) and
     * counter (e.g. a transaction recorded) metrics.
     *
     * @throws QuotaExceededException
     */
    public function increment(string $code, int $amount = 1, array $meta = []): ?QuotaUsage
    {
        return DB::transaction(function () use ($code, $amount, $meta) {
            $metric = $this->metric($code);
            if (! $metric) {
                return null;
            }
            
            $usage  = $this->lockedUsageRow($metric);

            $limit = $this->limit($code);
            $new   = $usage->used + $amount;

            if (config('keloola-quota.strict', true) && $limit !== null && $new > $limit) {
                throw new QuotaExceededException($code, $limit, $new, $usage->used);
            }

            $usage->used = $new;
            $usage->save();

            $this->log($usage, 'increment', $amount, $meta);

            return $usage;
        });
    }

    /**
     * Decrement usage. Mainly for snapshot metrics (e.g. a user removed,
     * storage freed). Counters generally only go up until reset.
     */
    public function decrement(string $code, int $amount = 1, array $meta = []): ?QuotaUsage
    {
        return DB::transaction(function () use ($code, $amount, $meta) {
            $metric = $this->metric($code);
            if (! $metric) {
                return null;
            }
            
            $usage  = $this->lockedUsageRow($metric);

            $usage->used = max(0, $usage->used - $amount);
            $usage->save();

            $this->log($usage, 'decrement', -$amount, $meta);

            return $usage;
        });
    }

    /**
     * Set an absolute value. Ideal for snapshot metrics where you can
     * recompute the true count (e.g. storage recalculated from files).
     */
    public function set(string $code, int $value, array $meta = []): ?QuotaUsage
    {
        return DB::transaction(function () use ($code, $value, $meta) {
            $metric = $this->metric($code);
            if (! $metric) {
                return null;
            }
            
            $usage  = $this->lockedUsageRow($metric);

            $delta = $value - $usage->used;
            $usage->used = max(0, $value);
            $usage->save();

            $this->log($usage, 'set', $delta, $meta);

            return $usage;
        });
    }

    /* ----------------------------------------------------------------------
     | Counter resets
     |----------------------------------------------------------------------*/

    /**
     * Reset all counter-type metrics for the active organization whose
     * period key has rolled over. Safe to call on every request or via
     * the scheduled command.
     */
    public function syncCounterPeriods(): int
    {
        $this->assertOrganization();

        $currentPeriod = $this->currentPeriodKey();
        $reset = 0;

        $counters = QuotaMetric::query()
            ->when($this->appId, fn ($q) => $q->forApp($this->appId))
            ->where('type', QuotaMetric::TYPE_COUNTER)
            ->get();

        foreach ($counters as $metric) {
            $usage = $this->usageRow($metric, create: false);

            if ($usage && $usage->period_key !== $currentPeriod) {
                DB::transaction(function () use ($usage, $currentPeriod) {
                    $usage->used = 0;
                    $usage->period_key = $currentPeriod;
                    $usage->reset_at = now();
                    $usage->save();
                    $this->log($usage, 'reset', 0, ['period' => $currentPeriod]);
                });
                $reset++;
            }
        }

        return $reset;
    }

    /* ----------------------------------------------------------------------
     | Snapshot / report
     |----------------------------------------------------------------------*/

    /**
     * Full quota report for the active org/plan, keyed by metric code.
     */
    public function report(): array
    {
        $this->assertOrganization();

        $metrics = QuotaMetric::query()
            ->when($this->appId, fn ($q) => $q->forApp($this->appId))
            ->active()
            ->get();

        return $metrics->mapWithKeys(function (QuotaMetric $metric) {
            $limit = $this->limit($metric->code);
            $used  = $this->used($metric->code);

            return [$metric->code => [
                'name'         => $metric->name,
                'type'         => $metric->type,
                'unit'         => $metric->unit,
                'used'         => $used,
                'limit'        => $limit,
                'is_unlimited' => $this->isUnlimited($metric->code),
                'remaining'    => $limit === null ? null : max(0, $limit - $used),
                'percent'      => $limit ? round(($used / $limit) * 100, 1) : 0.0,
            ]];
        })->toArray();
    }

    /* ----------------------------------------------------------------------
     | Internals
     |----------------------------------------------------------------------*/

    protected function metric(string $code): ?QuotaMetric
    {
        $this->assertApp();

        $metric = QuotaMetric::forApp($this->appId)
            ->where('code', $code)
            ->first();

        if (! $metric) {
            \Illuminate\Support\Facades\Log::warning("Quota metric not found: {$code} for app {$this->appId}");
            return null;
        }

        return $metric;
    }

    protected function planQuota(int $metricId): ?AppPlanQuota
    {
        if (! $this->appPlanId) {
            return null;
        }

        return AppPlanQuota::forPlan($this->appPlanId)
            ->where('quota_metric_id', $metricId)
            ->first();
    }

    protected function usageRow(QuotaMetric $metric, bool $create = true): ?QuotaUsage
    {
        $this->assertOrganization();

        $periodKey = $metric->isCounter() ? $this->currentPeriodKey() : null;

        $query = QuotaUsage::where('organization_id', $this->organizationId)
            ->where('quota_metric_id', $metric->id)
            ->where('period_key', $periodKey);

        $usage = $query->first();

        if (! $usage && $create) {
            $usage = QuotaUsage::create([
                'organization_id' => $this->organizationId,
                'quota_metric_id' => $metric->id,
                'used'            => 0,
                'period_key'      => $periodKey,
                'reset_at'        => $metric->isCounter() ? now() : null,
            ]);
        }

        return $usage;
    }

    protected function lockedUsageRow(QuotaMetric $metric): QuotaUsage
    {
        // Ensure a row exists, then lock it for the rest of the transaction.
        $usage = $this->usageRow($metric, create: true);

        return QuotaUsage::where('id', $usage->id)->lockForUpdate()->first();
    }

    protected function log(QuotaUsage $usage, string $action, int $delta, array $meta): void
    {
        QuotaUsageLog::create([
            'quota_usage_id'  => $usage->id,
            'organization_id' => $this->organizationId,
            'action'          => $action,
            'delta'           => $delta,
            'balance_after'   => $usage->used,
            'period_key'      => $usage->period_key,
            'meta'            => $meta ?: null,
        ]);
    }

    protected function currentPeriodKey(): string
    {
        return now()->format(config('keloola-quota.period.format', 'Y-m'));
    }

    protected function assertApp(): void
    {
        if (! $this->appId) {
            throw new \LogicException('No app scoped. Call ->app($appId) first.');
        }
    }

    protected function assertOrganization(): void
    {
        if (! $this->organizationId) {
            throw new \LogicException('No organization scoped. Call ->for($organizationId) first.');
        }
    }
}
