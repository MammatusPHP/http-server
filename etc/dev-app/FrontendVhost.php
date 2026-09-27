<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Groups\Attributes\Group;
use Mammatus\Groups\Type;
use Mammatus\Http\Server\Configuration\Vhost;
use Mammatus\Http\Server\Configuration\Webroot;
use Mammatus\Http\Server\Configuration\WebSocketVhost;
use Mammatus\Http\Server\Webroot\NoWebroot;
use Mammatus\Http\Server\WebSocket\WebSocketDefaults;
use Mammatus\Kubernetes\Attributes\Ingress;
use Mammatus\Kubernetes\Attributes\Service;
use Psr\Http\Server\MiddlewareInterface;

#[Group(Type::Daemon, 'frontend')]
#[Service]
#[Ingress('www.example.com')]
final class FrontendVhost implements Vhost, WebSocketVhost
{
    private const string SERVER_NAME = 'frontend';
    private const int LISTEN_PORT    = 1337;

    public static function port(): int
    {
        return self::LISTEN_PORT;
    }

    public static function name(): string
    {
        return self::SERVER_NAME;
    }

    public static function webroot(): Webroot
    {
        return new NoWebroot();
    }

    public static function maxConcurrentRequests(): null
    {
        return null;
    }

    public static function webSocketHeartbeatIntervalSeconds(): float
    {
        return WebSocketDefaults::HEARTBEAT_INTERVAL_SECONDS;
    }

    public static function webSocketServeClientAsset(): bool
    {
        return true;
    }

    /** @return iterable<MiddlewareInterface> */
    public function middleware(): iterable
    {
        yield from [];
    }
}
