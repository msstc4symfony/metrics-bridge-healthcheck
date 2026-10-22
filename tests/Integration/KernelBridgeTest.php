<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Integration;

use Monolog\Logger;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\ActionInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Request;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Enum\CheckTypeEnum;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetric;
use Msstc4Symfony\MetricsBridgeHealthcheck\Infrastructure\Metrics\HealthcheckMetricsListener;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\ExceptionHandlerSnapshot;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures\InMemoryRegistry;
use Msstc4Symfony\MetricsBridgeHealthcheck\Test\Integration\Kernel\TestKernel;
use Msstc4Symfony\MetricsBundle\Application\LogChannel;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

#[CoversNothing]
final class KernelBridgeTest extends TestCase
{
    private const CHECKER_RUN = 'symfony_healthcheck_checker_run';

    private const CHECKER_DURATION = 'symfony_healthcheck_checker_duration_histogram_seconds';

    private const PROBE_RUN = 'symfony_healthcheck_probe_run';

    private ?TestKernel $kernel = null;

    /** @var list<string> */
    private array $bundleDeprecations = [];

    private ExceptionHandlerSnapshot $exceptionHandlers;

    #[Override]
    protected function setUp(): void
    {
        // trigger_deprecation() silences its notice with @, which PHPUnit ignores; record it directly.
        set_error_handler(function (int $level, string $message): bool {
            if (str_contains($message, 'msstc4symfony/')) {
                $this->bundleDeprecations[] = $message;
            }

            return false;
        }, E_USER_DEPRECATED);
        (new Filesystem())->remove(TestKernel::cacheRoot());
        $this->exceptionHandlers = ExceptionHandlerSnapshot::take();
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        $this->kernel?->shutdown();

        // FrameworkBundle::boot() on symfony/error-handler before 7.4.17 leaves its exception handler
        // installed when another error handler is active, which PHPUnit reports as risky.
        if (isset($this->exceptionHandlers)) {
            $this->exceptionHandlers->restore();
        }

        (new Filesystem())->remove(TestKernel::cacheRoot());
    }

    public function testAReadinessRunCountsEveryCheckerAndTheProbe(): void
    {
        $this->action($this->kernel())->run(new Request(CheckTypeEnum::READINESS));

        $registry = $this->registry($this->kernel());
        self::assertEqualsCanonicalizing([
            [['shop', 'api', 'app.checker.down', 'readiness', 'failed'], 1.0],
            [['shop', 'api', 'app.checker.optional', 'readiness', 'degraded'], 1.0],
            [['shop', 'api', 'app.checker.up', 'readiness', 'passed'], 1.0],
        ], InMemoryRegistry::values($registry, self::CHECKER_RUN));
        self::assertEqualsCanonicalizing([
            [['shop', 'api', 'app.checker.down', 'readiness'], 1.0],
            [['shop', 'api', 'app.checker.optional', 'readiness'], 1.0],
            [['shop', 'api', 'app.checker.up', 'readiness'], 1.0],
        ], InMemoryRegistry::counts($registry, self::CHECKER_DURATION));
        self::assertSame([[['shop', 'api', 'readiness', 'failed'], 1.0]], InMemoryRegistry::values($registry, self::PROBE_RUN));
        self::assertSame([], $this->bundleDeprecations);
    }

    public function testALivelinessRunIsCountedUnderItsProbe(): void
    {
        $this->action($this->kernel())->run(new Request(CheckTypeEnum::LIVELINESS));

        self::assertSame([[['shop', 'api', 'liveliness', 'failed'], 1.0]], InMemoryRegistry::values($this->registry($this->kernel()), self::PROBE_RUN));
    }

    public function testTheParallelExecutionIsCountedTheSameWay(): void
    {
        $kernel = $this->boot('parallel', ['execution' => 'parallel']);

        $this->action($kernel)->run(new Request(CheckTypeEnum::READINESS));

        self::assertEqualsCanonicalizing([
            [['shop', 'api', 'app.checker.down', 'readiness', 'failed'], 1.0],
            [['shop', 'api', 'app.checker.optional', 'readiness', 'degraded'], 1.0],
            [['shop', 'api', 'app.checker.up', 'readiness', 'passed'], 1.0],
        ], InMemoryRegistry::values($this->registry($kernel), self::CHECKER_RUN));
    }

    public function testARunTheResultCacheAnswersCountsNothing(): void
    {
        $kernel = $this->boot('cached', ['cache' => ['enabled' => true, 'ttl_seconds' => 60]], false);
        $action = $this->action($kernel);

        $action->run(new Request(CheckTypeEnum::READINESS));
        $action->run(new Request(CheckTypeEnum::READINESS));

        self::assertSame([[['shop', 'api', 'readiness', 'passed'], 1.0]], InMemoryRegistry::values($this->registry($kernel), self::PROBE_RUN));
        self::assertSame([[['shop', 'api', 'app.checker.up', 'readiness', 'passed'], 1.0]], InMemoryRegistry::values($this->registry($kernel), self::CHECKER_RUN));
    }

    public function testMetricsBundleKnowsTheMetrics(): void
    {
        $enums = $this->kernel()->getContainer()->getParameter('msstc4symfony_metrics.metric_enums');
        self::assertIsArray($enums);
        self::assertContains(HealthcheckMetric::class, $enums);
    }

    public function testTheListenerLogsToTheUndecoratedMetricsChannel(): void
    {
        $listener = $this->container($this->kernel())->get(HealthcheckMetricsListener::class);
        self::assertInstanceOf(HealthcheckMetricsListener::class, $listener);
        $logger = (new ReflectionProperty($listener, 'logger'))->getValue($listener);
        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame(LogChannel::NAME, $logger->getName());
    }

    private function kernel(): TestKernel
    {
        return $this->kernel ?? $this->boot('test', []);
    }

    /**
     * @param array<string, scalar|list<string>|array<string, scalar>> $healthcheck
     */
    private function boot(string $environment, array $healthcheck, bool $withFailingCheckers = true): TestKernel
    {
        self::assertNull($this->kernel, 'One kernel per test');
        $this->kernel = new TestKernel($environment, $healthcheck, $withFailingCheckers);
        $this->kernel->boot();

        return $this->kernel;
    }

    private function action(TestKernel $kernel): ActionInterface
    {
        $action = $this->container($kernel)->get(ActionInterface::class);
        self::assertInstanceOf(ActionInterface::class, $action);

        return $action;
    }

    private function registry(TestKernel $kernel): CollectorRegistry
    {
        $registry = $this->container($kernel)->get(RegistryInterface::class);
        self::assertInstanceOf(CollectorRegistry::class, $registry);

        return $registry;
    }

    private function container(TestKernel $kernel): ContainerInterface
    {
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
