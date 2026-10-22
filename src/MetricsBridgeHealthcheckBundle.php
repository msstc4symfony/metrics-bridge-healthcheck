<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck;

use Msstc4Symfony\MetricsBridgeHealthcheck\DependencyInjection\CompilerPass\RegisterHealthcheckMetricsPass;
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class MetricsBridgeHealthcheckBundle extends AbstractBundle
{
    protected string $extensionAlias = 'msstc4symfony_metrics_bridge_healthcheck';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterHealthcheckMetricsPass());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/Resources/config/services.php');
    }
}
