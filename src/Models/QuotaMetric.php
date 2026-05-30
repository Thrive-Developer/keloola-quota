<?php

namespace Keloola\Quota\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotaMetric extends Model
{
    public const TYPE_SNAPSHOT = 'snapshot';
    public const TYPE_COUNTER  = 'counter';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getTable()
    {
        return config('keloola-quota.tables.quota_metrics', 'quota_metrics');
    }

    public function planQuotas(): HasMany
    {
        return $this->hasMany(
            config('keloola-quota.models.app_plan_quota', AppPlanQuota::class),
            'quota_metric_id'
        );
    }

    public function usages(): HasMany
    {
        return $this->hasMany(
            config('keloola-quota.models.quota_usage', QuotaUsage::class),
            'quota_metric_id'
        );
    }

    public function isSnapshot(): bool
    {
        return $this->type === self::TYPE_SNAPSHOT;
    }

    public function isCounter(): bool
    {
        return $this->type === self::TYPE_COUNTER;
    }

    public function scopeForApp($query, $appId)
    {
        return $query->where('app_id', $appId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
