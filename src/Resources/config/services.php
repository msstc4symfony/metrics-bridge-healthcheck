<?php

declare(strict_types=1);

use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetricsListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(HealthcheckMetricsListener::class);
};
