<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table       = config('keloola-quota.tables.quota_usages', 'quota_usages');
        $metricTable = config('keloola-quota.tables.quota_metrics', 'quota_metrics');
        $orgRef      = config('keloola-quota.references.organization');

        Schema::create($table, function (Blueprint $t) use ($metricTable, $orgRef) {
            $t->id();

            // Organization reference (the tenant consuming the quota).
            if (($orgRef['type'] ?? 'uuid') === 'uuid') {
                $t->uuid('organization_id');
            } else {
                $t->unsignedBigInteger('organization_id');
            }

            $t->foreignId('quota_metric_id')
                ->constrained($metricTable)
                ->cascadeOnDelete();

            // Current usage value.
            //  - snapshot: live count (e.g. current users, storage in use)
            //  - counter : accumulated count within the current period
            $t->bigInteger('used')->default(0);

            // Period key only relevant for counter metrics, e.g. "2026-05".
            // Null for snapshot metrics.
            $t->string('period_key')->nullable();

            // When the counter was last reset (counter type only).
            $t->timestamp('reset_at')->nullable();

            $t->timestamps();

            // One usage row per org + metric + period.
            $t->unique(
                ['organization_id', 'quota_metric_id', 'period_key'],
                'quota_usage_unique'
            );
            $t->index(['organization_id', 'quota_metric_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('keloola-quota.tables.quota_usages', 'quota_usages'));
    }
};
