<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Unit;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Event\HealthCheckerCompletedEvent;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\CheckerRunStatus;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\ProbeRunStatus;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\StaticChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckerRunStatus::class)]
#[CoversClass(ProbeRunStatus::class)]
final class RunStatusTest extends TestCase
{
    #[DataProvider('checkerOutcomes')]
    public function testAnErrorWinsOverAWarning(bool $success, bool $warned, CheckerRunStatus $expected): void
    {
        self::assertSame($expected, CheckerRunStatus::of(new HealthCheckerCompletedEvent(StaticChecker::class, 1.0, $success, null, null, $warned)));
    }

    /**
     * @return iterable<string, array{bool, bool, CheckerRunStatus}>
     */
    public static function checkerOutcomes(): iterable
    {
        yield 'passed' => [true, false, CheckerRunStatus::PASSED];
        yield 'warned' => [true, true, CheckerRunStatus::DEGRADED];
        yield 'failed' => [false, false, CheckerRunStatus::FAILED];
        yield 'failed and warned' => [false, true, CheckerRunStatus::FAILED];
    }

    public function testAProbePassesWhenItsResponseDoes(): void
    {
        self::assertSame([ProbeRunStatus::PASSED, ProbeRunStatus::FAILED], [ProbeRunStatus::of(true), ProbeRunStatus::of(false)]);
    }
}
