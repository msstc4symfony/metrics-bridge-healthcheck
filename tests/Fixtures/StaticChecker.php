<?php

declare(strict_types=1);

namespace Msstc4Symfony\MetricsBridgeHealthcheck\Test\Fixtures;

use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\Checker\CheckInterface;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\CheckResult;
use Msstc4Symfony\HealthCheckBundle\Application\Health\Check\DTO\Context;
use Override;

final readonly class StaticChecker implements CheckInterface
{
    public function __construct(
        private bool $passes,
    ) {
    }

    #[Override]
    public function isSupport(Context $context): bool
    {
        return true;
    }

    #[Override]
    public function check(CheckResult $result, Context $context): CheckResult
    {
        if ($this->passes) {
            $result->addMessage('static passed');
        } else {
            $result->addError('static failed');
        }

        return $result;
    }
}
