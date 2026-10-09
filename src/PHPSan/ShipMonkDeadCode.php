<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\PHPSan;

use Mammatus\DevApp\Http\Server\DemoWebSocketPageHandler;
use Mammatus\DevApp\Http\Server\FrontendVhost;
use Mammatus\DevApp\Http\Server\HomePageHandler;
use Mammatus\DevApp\Http\Server\PingHandler;
use Mammatus\DevApp\Http\Server\WebSocketDemoEvent;
use Mammatus\DevApp\Http\Server\WebSocketPingHandler;
use Mammatus\DevApp\Http\Server\WebSocketPingParams;
use Mammatus\DevApp\Http\Server\WebSocketPingResult;
use Mammatus\Vhost\Healthz\HealthCheckVhost;
use Mammatus\Vhost\Healthz\HealthzHandler;
use Mammatus\Vhost\Healthz\IndexHandler;
use Mammatus\Vhost\Healthz\LivenessProbeHandler;
use Mammatus\Vhost\Healthz\ReadinessProbeHandler;
use Mammatus\Vhost\Healthz\StartUpProbeHandler;
use Override;
use ReflectionMethod;
use ReflectionProperty;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

use function str_starts_with;

final class ShipMonkDeadCode extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): VirtualUsageData|null
    {
        /**
         * vhost: frontend
         */
        if ($method->getDeclaringClass()->getName() === FrontendVhost::class) {
            return VirtualUsageData::withNote('Class is a Vhost');
        }

        if ($method->getDeclaringClass()->getName() === HomePageHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === DemoWebSocketPageHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === PingHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === WebSocketPingHandler::class) {
            return VirtualUsageData::withNote('Class is a WebSocket Handler');
        }

        if ($method->getDeclaringClass()->getName() === WebSocketPingParams::class) {
            return VirtualUsageData::withNote('Class is a WebSocket RPC params DTO');
        }

        if ($method->getDeclaringClass()->getName() === WebSocketPingResult::class) {
            return VirtualUsageData::withNote('Class is a WebSocket RPC return DTO');
        }

        if ($method->getDeclaringClass()->getName() === WebSocketDemoEvent::class) {
            return VirtualUsageData::withNote('Class is a WebSocket channel payload');
        }

                /**
         * vhost: healthz
         */
        if ($method->getDeclaringClass()->getName() === HealthCheckVhost::class) {
            return VirtualUsageData::withNote('Class is a Vhost');
        }

        if ($method->getDeclaringClass()->getName() === IndexHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === HealthzHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === LivenessProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === ReadinessProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

        if ($method->getDeclaringClass()->getName() === StartUpProbeHandler::class) {
            return VirtualUsageData::withNote('Class is a Handler');
        }

                                $webSocketRuntime = str_starts_with($method->getDeclaringClass()->getName(), 'Mammatus\\Http\\Server\\WebSocket\\');

        if ($webSocketRuntime) {
            return VirtualUsageData::withNote('WebSocket runtime (wired from generated server)');
        }

        return null;
    }

    #[Override]
    protected function shouldMarkPropertyAsRead(ReflectionProperty $property): VirtualUsageData|null
    {
        if ($property->getDeclaringClass()->getName() === WebSocketPingParams::class) {
            return VirtualUsageData::withNote('WebSocket RPC params DTO property (hydrated at runtime)');
        }

        if ($property->getDeclaringClass()->getName() === WebSocketPingResult::class) {
            return VirtualUsageData::withNote('WebSocket RPC return DTO property (serialized at runtime)');
        }

        if ($property->getDeclaringClass()->getName() === WebSocketDemoEvent::class) {
            return VirtualUsageData::withNote('WebSocket channel payload property (pub/sub at runtime)');
        }

                                return null;
    }

    #[Override]
    protected function shouldMarkPropertyAsWritten(ReflectionProperty $property): VirtualUsageData|null
    {
        return $this->shouldMarkPropertyAsRead($property);
    }
}
