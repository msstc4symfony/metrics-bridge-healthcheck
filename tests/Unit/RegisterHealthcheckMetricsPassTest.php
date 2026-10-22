<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Unit;

use LogicException;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use Msstc4Symfony\MetricsBridgeHealthcheck\DependencyInjection\CompilerPass\RegisterHealthcheckMetricsPass;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetric;
use Msstc4Symfony\MetricsBundle\MetricsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(RegisterHealthcheckMetricsPass::class)]
final class RegisterHealthcheckMetricsPassTest extends TestCase
{
    public function testAppendsTheMetricOnce(): void
    {
        $container = $this->withHealthCheckBundle();
        $container->setParameter('msstc4symfony_metrics.metric_enums', ['App\Metric']);

        (new RegisterHealthcheckMetricsPass())->process($container);
        (new RegisterHealthcheckMetricsPass())->process($container);

        self::assertSame(['App\Metric', HealthcheckMetric::class], $container->getParameter('msstc4symfony_metrics.metric_enums'));
    }

    public function testFailsWithoutMetricsBundle(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('MetricsBridgeHealthcheckBundle needs MetricsBundle: register %s in config/bundles.php.', MetricsBundle::class));

        (new RegisterHealthcheckMetricsPass())->process($this->withHealthCheckBundle());
    }

    public function testFailsWithoutHealthCheckBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('msstc4symfony_metrics.metric_enums', []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('MetricsBridgeHealthcheckBundle needs HealthCheckBundle: register %s in config/bundles.php.', HealthCheckBundle::class));

        (new RegisterHealthcheckMetricsPass())->process($container);
    }

    public function testRejectsANonListParameter(): void
    {
        $container = $this->withHealthCheckBundle();
        $container->setParameter('msstc4symfony_metrics.metric_enums', 'oops');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be a list of enum classes, got string');

        (new RegisterHealthcheckMetricsPass())->process($container);
    }

    private function withHealthCheckBundle(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension((new HealthCheckBundle())->getContainerExtension() ?? self::fail('HealthCheckBundle has no extension'));

        return $container;
    }
}
