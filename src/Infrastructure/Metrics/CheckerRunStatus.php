<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;

enum CheckerRunStatus: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';

    /** Passed with a warning, as a non-critical checker that failed does. */
    case DEGRADED = 'degraded';

    public static function of(HealthCheckerCompletedEvent $event): self
    {
        return match (true) {
            !$event->success => self::FAILED,
            $event->warned => self::DEGRADED,
            default => self::PASSED,
        };
    }
}
