<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures;

use Msstc4Symfony\MetricsBundle\Application\Metric\MetricDefinitionInterface;
use Msstc4Symfony\MetricsBundle\Application\Port\MetricRecorderInterface;
use Override;
use Throwable;

/**
 * @phpstan-type RecordedCall array{method: 'increment'|'set'|'observe', metric: MetricDefinitionInterface, value: float|int|null, labels: list<scalar>}
 */
final class RecordingMetricRecorder implements MetricRecorderInterface
{
    /** @var list<RecordedCall> */
    private array $calls = [];

    /**
     * @param list<'increment'|'set'|'observe'> $failingMethods the methods that throw $failure; all when empty
     */
    public function __construct(
        private readonly ?Throwable $failure = null,
        private readonly array $failingMethods = [],
    ) {
    }

    #[Override]
    public function increment(MetricDefinitionInterface $metric, array $labels = []): void
    {
        $this->record('increment', $metric, null, $labels);
    }

    #[Override]
    public function set(MetricDefinitionInterface $metric, float|int $value, array $labels = []): void
    {
        $this->record('set', $metric, $value, $labels);
    }

    #[Override]
    public function observe(MetricDefinitionInterface $metric, float $value, array $labels = []): void
    {
        $this->record('observe', $metric, $value, $labels);
    }

    /**
     * @return list<RecordedCall>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @param 'increment'|'set'|'observe' $method
     * @param list<scalar> $labels
     */
    private function record(string $method, MetricDefinitionInterface $metric, float|int|null $value, array $labels): void
    {
        if ($this->failure instanceof Throwable && ($this->failingMethods === [] || in_array($method, $this->failingMethods, true))) {
            throw $this->failure;
        }

        $this->calls[] = ['method' => $method, 'metric' => $metric, 'value' => $value, 'labels' => $labels];
    }
}
