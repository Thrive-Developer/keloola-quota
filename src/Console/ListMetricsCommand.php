<?php

namespace Keloola\Quota\Console;

use Illuminate\Console\Command;
use Keloola\Quota\Models\QuotaMetric;

class ListMetricsCommand extends Command
{
    protected $signature = 'keloola-quota:metrics {--app= : The ID of the application to filter metrics}';
    protected $description = 'List all available quota metrics';

    public function handle(): void
    {
        $appId = $this->option('app') ?? config('keloola-quota.provisioning.app_id');

        $query = QuotaMetric::query();

        if ($appId) {
            $query->forApp($appId);
            $this->info("Showing metrics for App ID: {$appId}");
        } else {
            $this->info("Showing metrics for all applications (App ID not specified)");
        }

        $metrics = $query->get(['id', 'app_id', 'name', 'code', 'type', 'unit', 'is_active']);

        if ($metrics->isEmpty()) {
            $this->warn('No quota metrics found.');
            return;
        }

        $this->table(
            ['ID', 'App ID', 'Name', 'Code', 'Type', 'Unit', 'Status'],
            $metrics->map(function ($metric) {
                return [
                    $metric->id,
                    $metric->app_id,
                    $metric->name,
                    $metric->code,
                    $metric->type,
                    $metric->unit ?: '-',
                    $metric->is_active ? '<info>Active</info>' : '<error>Inactive</error>',
                ];
            })
        );
    }
}
