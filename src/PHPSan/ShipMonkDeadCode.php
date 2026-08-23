<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\PHPSan;

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
         * vhost: frontend
         */
        if ($method->getDeclaringClass()->getName() === \Mammatus\DevApp\Http\Server\FrontendVhost::class) {
            return VirtualUsageData::withNote('Class is a Vhost');
        }

        if ($method->getDeclaringClass()->getName() === \Mammatus\DevApp\Http\Server\HomePageHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        if ($method->getDeclaringClass()->getName() === \Mammatus\DevApp\Http\Server\PingHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        
        /**
         * vhost: healthz
         */
        if ($method->getDeclaringClass()->getName() === \Mammatus\Vhost\Healthz\HealthCheckVhost::class) {
            return VirtualUsageData::withNote('Class is a Vhost');
        }

        if ($method->getDeclaringClass()->getName() === \Mammatus\Vhost\Healthz\IndexHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        if ($method->getDeclaringClass()->getName() === \Mammatus\Vhost\Healthz\HealthzHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        if ($method->getDeclaringClass()->getName() === \Mammatus\Vhost\Healthz\LivenessProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        if ($method->getDeclaringClass()->getName() === \Mammatus\Vhost\Healthz\ReadinessProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        if ($method->getDeclaringClass()->getName() === \Mammatus\Vhost\Healthz\StartUpProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }
        
        return null;
    }
}
