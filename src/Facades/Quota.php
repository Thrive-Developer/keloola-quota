<?php

namespace Keloola\Quota\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Keloola\Quota\Services\QuotaManager app(string|int $appId)
 * @method static \Keloola\Quota\Services\QuotaManager for(string|int $organizationId)
 * @method static \Keloola\Quota\Services\QuotaManager plan(string|int $appPlanId)
 * @method static ?int limit(string $code)
 * @method static bool isUnlimited(string $code)
 * @method static int used(string $code)
 * @method static ?int remaining(string $code)
 * @method static bool canConsume(string $code, int $amount = 1)
 * @method static bool hasReached(string $code)
 * @method static \Keloola\Quota\Models\QuotaUsage increment(string $code, int $amount = 1, array $meta = [])
 * @method static \Keloola\Quota\Models\QuotaUsage decrement(string $code, int $amount = 1, array $meta = [])
 * @method static \Keloola\Quota\Models\QuotaUsage set(string $code, int $value, array $meta = [])
 * @method static int syncCounterPeriods()
 * @method static array report()
 *
 * @see \Keloola\Quota\Services\QuotaManager
 */
class Quota extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'keloola.quota';
    }
}
