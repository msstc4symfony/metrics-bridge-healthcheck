<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\DependencyInjection\CompilerPass;

use LogicException;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetric;
use Msstc4Symfony\MetricsBundle\MetricsBundle;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
final class RegisterHealthcheckMetricsPass implements CompilerPassInterface
{
    private const METRIC_ENUMS_PARAMETER = 'msstc4symfony_metrics.metric_enums';

    private const HEALTHCHECK_EXTENSION = 'msstc4symfony_healthcheck';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::METRIC_ENUMS_PARAMETER)) {
            throw new LogicException(sprintf('MetricsBridgeHealthcheckBundle needs MetricsBundle: register %s in config/bundles.php.', MetricsBundle::class));
        }

        if (!$container->hasExtension(self::HEALTHCHECK_EXTENSION)) {
            throw new LogicException(sprintf('MetricsBridgeHealthcheckBundle needs HealthCheckBundle: register %s in config/bundles.php.', HealthCheckBundle::class));
        }

        $enums = $container->getParameter(self::METRIC_ENUMS_PARAMETER);
        if (!is_array($enums)) {
            throw new LogicException(sprintf('Parameter "%s" must be a list of enum classes, got %s.', self::METRIC_ENUMS_PARAMETER, get_debug_type($enums)));
        }

        if (!in_array(HealthcheckMetric::class, $enums, true)) {
            $enums[] = HealthcheckMetric::class;
        }

        $container->setParameter(self::METRIC_ENUMS_PARAMETER, $enums);
    }
}
