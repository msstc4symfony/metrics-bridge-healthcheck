<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics;

enum ProbeRunStatus: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';

    public static function of(bool $success): self
    {
        return $success ? self::PASSED : self::FAILED;
    }
}
