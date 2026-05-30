<?php

namespace Keloola\Quota\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppPlanQuota extends Model
{
    protected $guarded = [];

    protected $casts = [
        'limit'        => 'integer',
        'is_unlimited' => 'boolean',
    ];

    public function getTable()
    {
        return config('keloola-quota.tables.app_plan_quotas', 'app_plan_quotas');
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(
            config('keloola-quota.models.quota_metric', QuotaMetric::class),
            'quota_metric_id'
        );
    }

    public function scopeForPlan($query, $planId)
    {
        return $query->where('app_plan_id', $planId);
    }
}
