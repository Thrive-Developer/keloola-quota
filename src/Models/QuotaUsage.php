<?php

namespace Keloola\Quota\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotaUsage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'used'     => 'integer',
        'reset_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('keloola-quota.tables.quota_usages', 'quota_usages');
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(
            config('keloola-quota.models.quota_metric', QuotaMetric::class),
            'quota_metric_id'
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            config('keloola-quota.models.quota_usage_log', QuotaUsageLog::class),
            'quota_usage_id'
        );
    }
}
