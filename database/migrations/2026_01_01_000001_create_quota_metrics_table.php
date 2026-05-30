<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('keloola-quota.tables.quota_metrics', 'quota_metrics');
        $ref   = config('keloola-quota.references.app');

        Schema::create($table, function (Blueprint $t) use ($ref) {
            $t->id();

            // App reference (apps table is owned by the host app).
            if (($ref['type'] ?? 'unsignedBigInteger') === 'uuid') {
                $t->uuid('app_id');
            } else {
                $t->unsignedBigInteger('app_id');
            }

            $t->string('name');                 // Human label, e.g. "Jumlah Transaksi"
            $t->string('code');                 // Machine key, e.g. "transactions"
            $t->enum('type', ['snapshot', 'counter']); // snapshot=fixed, counter=monthly reset
            $t->string('unit')->nullable();     // e.g. "transactions", "GB", "users"
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['app_id', 'code']);
            $t->index('app_id');

            if (config('keloola-quota.references.constrained')) {
                $t->foreign('app_id')
                    ->references($ref['column'])
                    ->on($ref['table'])
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('keloola-quota.tables.quota_metrics', 'quota_metrics'));
    }
};
