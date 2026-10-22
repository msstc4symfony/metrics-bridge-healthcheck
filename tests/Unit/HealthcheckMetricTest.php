<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Unit;

use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\CheckerRunStatus;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetric;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\ProbeRunStatus;
use Msstc4Symfony\MetricsBundle\Application\Metric\Label;
use Msstc4Symfony\MetricsBundle\Application\Metric\LabelType;
use Msstc4Symfony\MetricsBundle\Application\Metric\MetricType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthcheckMetric::class)]
#[UsesClass(CheckerRunStatus::class)]
#[UsesClass(ProbeRunStatus::class)]
final class HealthcheckMetricTest extends TestCase
{
    public function testDeclaresTheCheckerAndProbeMetrics(): void
    {
        self::assertSame(
            [
                'healthcheck_checker_run' => MetricType::COUNTER,
                'healthcheck_checker_duration_histogram_seconds' => MetricType::HISTOGRAM,
                'healthcheck_probe_run' => MetricType::COUNTER,
            ],
            array_combine(
                array_map(static fn (HealthcheckMetric $metric): string => $metric->value, HealthcheckMetric::cases()),
                array_map(static fn (HealthcheckMetric $metric): MetricType => $metric->getType(), HealthcheckMetric::cases()),
            ),
        );
    }

    public function testEachMetricHasItsLabels(): void
    {
        self::assertSame(['checker', 'probe', 'status'], $this->names(HealthcheckMetric::CHECKER_RUN));
        self::assertSame(['checker', 'probe'], $this->names(HealthcheckMetric::CHECKER_DURATION));
        self::assertSame(['probe', 'status'], $this->names(HealthcheckMetric::PROBE_RUN));
    }

    public function testTheEnumLabelsListTheirValues(): void
    {
        [, $probe, $checkerStatus] = HealthcheckMetric::CHECKER_RUN->getLabels();
        [, $probeStatus] = HealthcheckMetric::PROBE_RUN->getLabels();

        self::assertSame([LabelType::ENUM, ['readiness', 'liveliness']], [$probe->type, $probe->enums]);
        self::assertSame([LabelType::ENUM, ['passed', 'failed', 'degraded']], [$checkerStatus->type, $checkerStatus->enums]);
        self::assertSame([LabelType::ENUM, ['passed', 'failed']], [$probeStatus->type, $probeStatus->enums]);
    }

    public function testOnlyTheDurationHasBuckets(): void
    {
        self::assertSame([0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0], HealthcheckMetric::CHECKER_DURATION->getBatches());
        self::assertSame([], HealthcheckMetric::CHECKER_RUN->getBatches());
        self::assertSame([], HealthcheckMetric::PROBE_RUN->getBatches());
    }

    public function testEveryMetricIsDescribed(): void
    {
        foreach (HealthcheckMetric::cases() as $metric) {
            self::assertNotSame('', $metric->getDescription(), $metric->value);
        }
    }

    /**
     * @return list<string>
     */
    private function names(HealthcheckMetric $metric): array
    {
        return array_map(static fn (Label $label): string => $label->name, $metric->getLabels());
    }
}
