<?php

namespace Keloola\Quota\Exceptions;

use Exception;

class QuotaExceededException extends Exception
{
    public function __construct(
        public string $metricCode,
        public int $limit,
        public int $attempted,
        public int $current
    ) {
        parent::__construct(
            "Quota exceeded for metric [{$metricCode}]. "
            . "Limit: {$limit}, current usage: {$current}, attempted to add: " . ($attempted - $current) . "."
        );
    }
}
