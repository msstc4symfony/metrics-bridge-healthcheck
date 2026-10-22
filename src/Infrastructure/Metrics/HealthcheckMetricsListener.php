<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics;

use Monolog\Attribute\WithMonologChannel;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use Msstc4Symfony\MetricsBundle\Application\LogChannel;
use Msstc4Symfony\MetricsBundle\Application\Port\MetricRecorderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

/**
 * Logs to metrics-bundle's channel: the application's error counter does not count it, so a failing metric never
 * feeds back into the metrics.
 *
 * @internal
 */
#[WithMonologChannel(LogChannel::NAME)]
final readonly class HealthcheckMetricsListener
{
    public function __construct(
        private MetricRecorderInterface $recorder,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[AsEventListener]
    public function onCheckerCompleted(HealthCheckerCompletedEvent $event): void
    {
        // Only an event built by hand lacks the probe, and a series without it would be mislabelled.
        if (!$event->type instanceof CheckTypeEnum) {
            return;
        }

        $checker = $event->checkerId ?? $event->checkerClass;
        try {
            $this->recorder->increment(HealthcheckMetric::CHECKER_RUN, [$checker, $event->type->value, CheckerRunStatus::of($event)->value]);
        } catch (Throwable $exception) {
            $this->reportFailure(HealthcheckMetric::CHECKER_RUN, $event->type, $exception);
        }

        try {
            $this->recorder->observe(HealthcheckMetric::CHECKER_DURATION, $event->durationMs / 1000, [$checker, $event->type->value]);
        } catch (Throwable $exception) {
            $this->reportFailure(HealthcheckMetric::CHECKER_DURATION, $event->type, $exception);
        }
    }

    #[AsEventListener]
    public function onRunCompleted(HealthCheckRunCompletedEvent $event): void
    {
        if (!$event->type instanceof CheckTypeEnum) {
            return;
        }

        try {
            $this->recorder->increment(HealthcheckMetric::PROBE_RUN, [$event->type->value, ProbeRunStatus::of($event->response->success)->value]);
        } catch (Throwable $exception) {
            $this->reportFailure(HealthcheckMetric::PROBE_RUN, $event->type, $exception);
        }
    }

    private function reportFailure(HealthcheckMetric $metric, CheckTypeEnum $probe, Throwable $exception): void
    {
        try {
            $this->logger?->error('Could not record the health check metric', [
                'metric' => $metric->value,
                'probe' => $probe->value,
                'exception' => $exception,
            ]);
        } catch (Throwable) {
            // A broken logger must not fail the probe either.
        }
    }
}
