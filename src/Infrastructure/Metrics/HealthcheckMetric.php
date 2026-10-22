<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\MetricsBundle\Application\Metric\Label;
use Msstc4Symfony\MetricsBundle\Application\Metric\LabelType;
use Msstc4Symfony\MetricsBundle\Application\Metric\MetricDefinitionInterface;
use Msstc4Symfony\MetricsBundle\Application\Metric\MetricType;
use Override;

/**
 * Counters, not gauges: replicas usually share the metrics storage, where a gauge would hold whichever one wrote last.
 */
enum HealthcheckMetric: string implements MetricDefinitionInterface
{
    case CHECKER_RUN = 'healthcheck_checker_run';
    case CHECKER_DURATION = 'healthcheck_checker_duration_histogram_seconds';
    case PROBE_RUN = 'healthcheck_probe_run';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEGRADED = 'degraded';

    #[Override]
    public function getType(): MetricType
    {
        return $this === self::CHECKER_DURATION ? MetricType::HISTOGRAM : MetricType::COUNTER;
    }

    #[Override]
    public function getDescription(): string
    {
        return match ($this) {
            self::CHECKER_RUN => 'Health checker runs by status; degraded passed with a warning, as a failed non-critical checker does',
            self::CHECKER_DURATION => 'Health checker duration in seconds',
            self::PROBE_RUN => 'Health probe runs by status',
        };
    }

    #[Override]
    public function getLabels(): array
    {
        $checker = new Label('checker', LabelType::STRING, 'Service id of the checker, or its class when it has none');
        $probe = new Label('probe', LabelType::ENUM, 'Probe type', array_map(static fn (CheckTypeEnum $type): string => $type->value, CheckTypeEnum::cases()));

        return match ($this) {
            self::CHECKER_RUN => [$checker, $probe, new Label('status', LabelType::ENUM, 'Run status', array_map(static fn (CheckerRunStatus $status): string => $status->value, CheckerRunStatus::cases()))],
            self::CHECKER_DURATION => [$checker, $probe],
            self::PROBE_RUN => [$probe, new Label('status', LabelType::ENUM, 'Run status', array_map(static fn (ProbeRunStatus $status): string => $status->value, ProbeRunStatus::cases()))],
        };
    }

    #[Override]
    public function getBatches(): array
    {
        return $this === self::CHECKER_DURATION ? [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0] : [];
    }
}
