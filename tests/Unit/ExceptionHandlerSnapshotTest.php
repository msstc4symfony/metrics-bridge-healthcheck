<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Unit;

use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\ExceptionHandlerSnapshot;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversNothing]
final class ExceptionHandlerSnapshotTest extends TestCase
{
    public function testRemovesHandlersPushedAfterTheSnapshot(): void
    {
        $outer = $this->current();
        $snapshot = ExceptionHandlerSnapshot::take();
        set_exception_handler(static function (Throwable $e): void {});
        set_exception_handler(static function (Throwable $e): void {});

        $snapshot->restore();

        self::assertSame($outer, $this->current());
    }

    public function testLeavesTheStackIntactWhenTheSnapshotHandlerIsGone(): void
    {
        $outer = $this->current();
        set_exception_handler(static function (Throwable $e): void {});
        $snapshot = ExceptionHandlerSnapshot::take();
        restore_exception_handler();
        $foreign = static function (Throwable $e): void {};
        set_exception_handler($foreign);

        $snapshot->restore();

        $current = $this->current();
        restore_exception_handler();
        self::assertSame($foreign, $current);
        self::assertSame($outer, $this->current());
    }

    /**
     * @return (callable(Throwable): void)|null
     */
    private function current(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
