<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table       = config('keloola-quota.tables.app_plan_quotas', 'app_plan_quotas');
        $metricTable = config('keloola-quota.tables.quota_metrics', 'quota_metrics');
        $planRef     = config('keloola-quota.references.app_plan');

        Schema::create($table, function (Blueprint $t) use ($metricTable, $planRef) {
            $t->id();

            // AppPlan reference (app_plans table is owned by the host app).
            if (($planRef['type'] ?? 'unsignedBigInteger') === 'uuid') {
                $t->uuid('app_plan_id');
            } else {
                $t->unsignedBigInteger('app_plan_id');
            }

            $t->foreignId('quota_metric_id')
                ->constrained($metricTable)
                ->cascadeOnDelete();

            // The numerical limit for this metric on this plan.
            $t->bigInteger('limit')->default(0);
            $t->boolean('is_unlimited')->default(false);
            $t->timestamps();

            $t->unique(['app_plan_id', 'quota_metric_id'], 'app_plan_quota_unique');
            $t->index('app_plan_id');

            if (config('keloola-quota.references.constrained')) {
                $t->foreign('app_plan_id')
                    ->references($planRef['column'])
                    ->on($planRef['table'])
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('keloola-quota.tables.app_plan_quotas', 'app_plan_quotas'));
    }
};
