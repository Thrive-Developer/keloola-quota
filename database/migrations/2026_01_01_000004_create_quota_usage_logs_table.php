<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table       = config('keloola-quota.tables.quota_usage_logs', 'quota_usage_logs');
        $usageTable  = config('keloola-quota.tables.quota_usages', 'quota_usages');
        $orgRef      = config('keloola-quota.references.organization');

        Schema::create($table, function (Blueprint $t) use ($usageTable, $orgRef) {
            $t->id();

            $t->foreignId('quota_usage_id')
                ->constrained($usageTable)
                ->cascadeOnDelete();

            // Organization / tenant the logged change belongs to. Everything is
            // scoped per organization; denormalized from quota_usages so the
            // audit trail can be filtered by organization without a join.
            if (($orgRef['type'] ?? 'uuid') === 'uuid') {
                $t->uuid('organization_id')->nullable();
            } else {
                $t->unsignedBigInteger('organization_id')->nullable();
            }

            $t->string('action');               // increment | decrement | set | reset
            $t->bigInteger('delta')->default(0);// change applied
            $t->bigInteger('balance_after');    // resulting used value
            $t->string('period_key')->nullable();
            $t->jsonb('meta')->nullable();       // arbitrary context (ref id, user, etc.)
            $t->timestamps();

            $t->index('quota_usage_id');
            $t->index(['organization_id', 'quota_usage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('keloola-quota.tables.quota_usage_logs', 'quota_usage_logs'));
    }
};
