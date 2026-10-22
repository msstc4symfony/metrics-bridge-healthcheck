<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Unit;

use Msstc4Symfony\MetricsBridgeHealthcheck\DependencyInjection\CompilerPass\RegisterHealthcheckMetricsPass;
use Msstc4Symfony\MetricsBridgeHealthcheck\MetricsBridgeHealthcheckBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(MetricsBridgeHealthcheckBundle::class)]
final class MetricsBridgeHealthcheckBundleTest extends TestCase
{
    public function testExtensionAliasFollowsTheFamilyNaming(): void
    {
        self::assertSame('msstc4symfony_metrics_bridge_healthcheck', (new MetricsBridgeHealthcheckBundle())->getContainerExtension()?->getAlias());
    }

    public function testBuildRegistersTheMetricPass(): void
    {
        $container = new ContainerBuilder();

        (new MetricsBridgeHealthcheckBundle())->build($container);

        $passes = array_map(get_class(...), $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(RegisterHealthcheckMetricsPass::class, $passes);
    }
}
