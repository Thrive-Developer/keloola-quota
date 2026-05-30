<?php

namespace Keloola\Quota\Exceptions;

use Exception;

class QuotaMetricNotFoundException extends Exception
{
    public static function forCode(string $appId, string $code): self
    {
        return new self("Quota metric [{$code}] not found for app [{$appId}].");
    }
}
