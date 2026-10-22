<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures;

use Prometheus\CollectorRegistry;
use Prometheus\MetricFamilySamples;

/**
 * Label values and value of one sample.
 *
 * @phpstan-type SampleTuple array{list<string>, float}
 */
final class InMemoryRegistry
{
    /**
     * Label values and value of every `_count` sample of the histogram.
     *
     * @return list<SampleTuple>
     */
    public static function counts(CollectorRegistry $registry, string $metricName): array
    {
        return self::samples($registry, $metricName, '_count');
    }

    /**
     * Label values and value of every sample of the counter.
     *
     * @return list<SampleTuple>
     */
    public static function values(CollectorRegistry $registry, string $metricName): array
    {
        return self::samples($registry, $metricName, '');
    }

    /**
     * @return list<SampleTuple>
     */
    private static function samples(CollectorRegistry $registry, string $metricName, string $suffix): array
    {
        $found = [];
        foreach ($registry->getMetricFamilySamples() as $family) {
            if (!$family instanceof MetricFamilySamples || $family->getName() !== $metricName) {
                continue;
            }

            foreach ($family->getSamples() as $sample) {
                if ($sample->getName() === $metricName . $suffix) {
                    $found[] = [array_values(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $sample->getLabelValues())), (float) $sample->getValue()];
                }
            }
        }

        return $found;
    }
}
