<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Integration\Kernel;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\HealthCheckBundle;
use Msstc4Symfony\MetricsBridgeHealthcheck\MetricsBridgeHealthcheckBundle;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\StaticChecker;
use Msstc4Symfony\MetricsBundle\MetricsBundle;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, scalar|list<string>|array<string, scalar>> $healthcheck the msstc4symfony_healthcheck configuration
     * @param bool $withFailingCheckers false registers only the passing checker, so the result cache keeps the response
     */
    public function __construct(
        string $environment,
        private readonly array $healthcheck = [],
        private readonly bool $withFailingCheckers = true,
    ) {
        parent::__construct($environment, false);
    }

    // Per process: parallel PHPUnit runs must not wipe each other's container.
    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/msstc4symfony-metrics-bridge-healthcheck-test-' . getmypid();
    }

    #[Override]
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new MonologBundle(), new MetricsBundle(), new HealthCheckBundle(), new MetricsBridgeHealthcheckBundle()];
    }

    #[Override]
    public function getCacheDir(): string
    {
        return self::cacheRoot() . '/cache/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return self::cacheRoot() . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'http_method_override' => false,
            'test' => true,
            // The php_errors logger installs a global handler that outlives the kernel and trips failOnRisky.
            'php_errors' => ['log' => false],
            'validation' => ['enabled' => true],
        ]);
        $container->extension('monolog', ['handlers' => ['main' => ['type' => 'test']]]);
        $container->extension('msstc4symfony_metrics', [
            'storage' => ['dsn' => 'inmemory://'],
            'application_name' => 'shop',
            'component_name' => 'api',
        ]);
        $services = $container->services();
        $services->set('app.checker.up', StaticChecker::class)->args([true])->tag(CheckInterface::class);
        $healthcheck = $this->healthcheck;
        if ($this->withFailingCheckers) {
            $services->set('app.checker.down', StaticChecker::class)->args([false])->tag(CheckInterface::class);
            $services->set('app.checker.optional', StaticChecker::class)->args([false])->tag(CheckInterface::class);
            $healthcheck['non_critical'] = ['app.checker.optional'];
        }

        $container->extension('msstc4symfony_healthcheck', $healthcheck);
    }
}
