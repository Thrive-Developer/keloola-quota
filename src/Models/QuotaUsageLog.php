<?php

namespace Keloola\Quota\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaUsageLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'delta'         => 'integer',
        'balance_after' => 'integer',
        'meta'          => 'array',
    ];

    public function getTable()
    {
        return config('keloola-quota.tables.quota_usage_logs', 'quota_usage_logs');
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(
            config('keloola-quota.models.quota_usage', QuotaUsage::class),
            'quota_usage_id'
        );
    }
}
