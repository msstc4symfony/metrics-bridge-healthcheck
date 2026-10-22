<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Unit;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Response;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckRunCompletedEvent;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\CheckerRunStatus;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetric;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetricsListener;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\ProbeRunStatus;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\RecordingMetricRecorder;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\StaticChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(HealthcheckMetricsListener::class)]
#[UsesClass(CheckerRunStatus::class)]
#[UsesClass(ProbeRunStatus::class)]
#[UsesClass(HealthcheckMetric::class)]
final class HealthcheckMetricsListenerTest extends TestCase
{
    public function testCountsTheRunAndObservesTheDurationOfACheckerByServiceId(): void
    {
        $recorder = new RecordingMetricRecorder();

        (new HealthcheckMetricsListener($recorder))->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 250.0, false, 'app.checker.redis', CheckTypeEnum::READINESS));

        self::assertSame([
            ['method' => 'increment', 'metric' => HealthcheckMetric::CHECKER_RUN, 'value' => null, 'labels' => ['app.checker.redis', 'readiness', 'failed']],
            ['method' => 'observe', 'metric' => HealthcheckMetric::CHECKER_DURATION, 'value' => 0.25, 'labels' => ['app.checker.redis', 'readiness']],
        ], $recorder->calls());
    }

    /**
     * @param 'passed'|'failed'|'degraded' $status
     */
    #[DataProvider('checkerOutcomes')]
    public function testTheCheckerStatusFollowsTheErrorAndWarningFlags(bool $success, bool $warned, string $status): void
    {
        $recorder = new RecordingMetricRecorder();

        (new HealthcheckMetricsListener($recorder))->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, $success, 'app.checker.db', CheckTypeEnum::LIVELINESS, $warned));

        self::assertSame(['app.checker.db', 'liveliness', $status], $recorder->calls()[0]['labels']);
    }

    /**
     * @return iterable<string, array{bool, bool, 'passed'|'failed'|'degraded'}>
     */
    public static function checkerOutcomes(): iterable
    {
        yield 'passed' => [true, false, 'passed'];
        yield 'failed' => [false, false, 'failed'];
        yield 'failed non-critical (a warning only)' => [true, true, 'degraded'];
        yield 'failed with a warning too' => [false, true, 'failed'];
    }

    public function testACheckerWithoutServiceIdIsNamedByItsClass(): void
    {
        $recorder = new RecordingMetricRecorder();

        (new HealthcheckMetricsListener($recorder))->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, true, null, CheckTypeEnum::READINESS));

        self::assertSame([StaticChecker::class, 'readiness', 'passed'], $recorder->calls()[0]['labels']);
    }

    public function testCountsWhetherTheProbePassed(): void
    {
        $recorder = new RecordingMetricRecorder();
        $listener = new HealthcheckMetricsListener($recorder);

        $listener->onRunCompleted(new HealthCheckRunCompletedEvent(new Response([], ['ok'], []), 3.0, CheckTypeEnum::READINESS));
        $listener->onRunCompleted(new HealthCheckRunCompletedEvent(new Response(['down'], [], []), 3.0, CheckTypeEnum::LIVELINESS));

        self::assertSame([
            ['method' => 'increment', 'metric' => HealthcheckMetric::PROBE_RUN, 'value' => null, 'labels' => ['readiness', 'passed']],
            ['method' => 'increment', 'metric' => HealthcheckMetric::PROBE_RUN, 'value' => null, 'labels' => ['liveliness', 'failed']],
        ], $recorder->calls());
    }

    public function testEventsWithoutAProbeTypeAreNotRecorded(): void
    {
        $recorder = new RecordingMetricRecorder();
        $listener = new HealthcheckMetricsListener($recorder);

        $listener->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, true, 'app.checker.db'));
        $listener->onRunCompleted(new HealthCheckRunCompletedEvent(new Response([], [], []), 1.0));

        self::assertSame([], $recorder->calls());
    }

    public function testARecorderFailureIsLoggedWithTheMetricItHit(): void
    {
        $handler = new TestHandler();
        $listener = new HealthcheckMetricsListener(new RecordingMetricRecorder(new RuntimeException('broken')), new Logger('metrics_bundle', [$handler]));

        $listener->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, true, 'app.checker.db', CheckTypeEnum::READINESS));
        $listener->onRunCompleted(new HealthCheckRunCompletedEvent(new Response([], [], []), 1.0, CheckTypeEnum::READINESS));

        self::assertSame(
            [
                ['Could not record the health check metric', ['metric' => 'healthcheck_checker_run', 'probe' => 'readiness']],
                ['Could not record the health check metric', ['metric' => 'healthcheck_checker_duration_histogram_seconds', 'probe' => 'readiness']],
                ['Could not record the health check metric', ['metric' => 'healthcheck_probe_run', 'probe' => 'readiness']],
            ],
            array_map(static fn (LogRecord $record): array => [$record->message, array_diff_key($record->context, ['exception' => true])], $handler->getRecords()),
        );
    }

    public function testTheDurationIsObservedWhenCountingTheRunFails(): void
    {
        $recorder = new RecordingMetricRecorder(new RuntimeException('broken'), ['increment']);

        (new HealthcheckMetricsListener($recorder))->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, true, 'app.checker.db', CheckTypeEnum::READINESS));

        self::assertSame([HealthcheckMetric::CHECKER_DURATION], array_column($recorder->calls(), 'metric'));
    }

    public function testABrokenLoggerDoesNotFailTheProbe(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(3))->method('error')->willThrowException(new RuntimeException('log handler down'));
        $listener = new HealthcheckMetricsListener(new RecordingMetricRecorder(new RuntimeException('broken')), $logger);

        $listener->onCheckerCompleted(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, true, 'app.checker.db', CheckTypeEnum::READINESS));
        $listener->onRunCompleted(new HealthCheckRunCompletedEvent(new Response([], [], []), 1.0, CheckTypeEnum::READINESS));
    }
}
