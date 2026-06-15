<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\PHPSan;

use Mammatus\Vhost\Healthz\HealthCheckVhost;
use Mammatus\Vhost\Healthz\HealthzHandler;
use Mammatus\Vhost\Healthz\IndexHandler;
use Mammatus\Vhost\Healthz\LivenessProbeHandler;
use Mammatus\Vhost\Healthz\ReadinessProbeHandler;
use Mammatus\Vhost\Healthz\StartUpProbeHandler;
use Override;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

final class ShipMonkDeadCode extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    public function shouldMarkMethodAsUsed(ReflectionMethod $method): VirtualUsageData|null
    {
        /**
         * vhost: healthz
         */
        if ($method->getDeclaringClass()->getName() === HealthCheckVhost::class) {
            return VirtualUsageData::withNote('Class is a Vhost');
        }

        if ($method->getDeclaringClass()->getName() === LivenessProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === IndexHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === ReadinessProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === StartUpProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === HealthzHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        return null;
    }
}
