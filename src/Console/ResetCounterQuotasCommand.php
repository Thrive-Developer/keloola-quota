<?php

namespace Keloola\Quota\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Keloola\Quota\Models\QuotaMetric;
use Keloola\Quota\Models\QuotaUsage;
use Keloola\Quota\Models\QuotaUsageLog;

class ResetCounterQuotasCommand extends Command
{
    protected $signature = 'keloola-quota:reset-counters
                            {--app= : Limit reset to a specific app_id}';

    protected $description = 'Reset all counter-type quota usages whose billing period has rolled over.';

    public function handle(): int
    {
        $period = now()->format(config('keloola-quota.period.format', 'Y-m'));

        $metricIds = QuotaMetric::query()
            ->where('type', QuotaMetric::TYPE_COUNTER)
            ->when($this->option('app'), fn ($q) => $q->where('app_id', $this->option('app')))
            ->pluck('id');

        if ($metricIds->isEmpty()) {
            $this->info('No counter metrics found.');
            return self::SUCCESS;
        }

        $stale = QuotaUsage::query()
            ->whereIn('quota_metric_id', $metricIds)
            ->where(function ($q) use ($period) {
                $q->whereNull('period_key')->orWhere('period_key', '!=', $period);
            })
            ->get();

        $count = 0;
        foreach ($stale as $usage) {
            DB::transaction(function () use ($usage, $period) {
                $usage->update([
                    'used'       => 0,
                    'period_key' => $period,
                    'reset_at'   => now(),
                ]);

                QuotaUsageLog::create([
                    'quota_usage_id' => $usage->id,
                    'action'         => 'reset',
                    'delta'          => 0,
                    'balance_after'  => 0,
                    'period_key'     => $period,
                    'meta'           => ['source' => 'quota:reset-counters'],
                ]);
            });
            $count++;
        }

        $this->info("Reset {$count} counter quota usage(s) for period {$period}.");

        return self::SUCCESS;
    }
}
