<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Mammatus\DevApp\Http\Server\FrontendVhost;
use Mammatus\DevApp\Http\Server\WebSocketDemoEvent;
use Mammatus\DevApp\Http\Server\WebSocketPingHandler;
use Mammatus\DevApp\Http\Server\WebSocketPingParams;
use Mammatus\DevApp\Http\Server\WebSocketPingResult;
use Mammatus\Groups\Attributes\Group;
use Mammatus\Groups\Type;
use Mammatus\Http\Server\Attributes\HttpMethod;
use Mammatus\Http\Server\Attributes\Route;
use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Channel;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc;
use Mammatus\Http\Server\Composer\Handler;
use Mammatus\Http\Server\Composer\Ingress;
use Mammatus\Http\Server\Composer\Server;
use Mammatus\Http\Server\Composer\Service;
use Mammatus\Http\Server\Composer\WebSocketChannelRegistration;
use Mammatus\Http\Server\Composer\WebSocketHandler;
use Mammatus\Vhost\Healthz\IndexHandler;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class ItemsTest extends TestCase
{
    #[Test]
    public function serviceJsonSerialize(): void
    {
        $service = new Service('frontend', 'app', 1337);

        self::assertSame(
            [
                'name' => 'frontend',
                'group' => 'app',
                'port' => 1337,
            ],
            $service->jsonSerialize(),
        );
    }

    #[Test]
    public function ingressJsonSerialize(): void
    {
        $ingress = new Ingress('frontend', 'www.example.test', '/');

        self::assertSame(
            [
                'name' => 'frontend',
                'host' => 'www.example.test',
                'path' => '/',
            ],
            $ingress->jsonSerialize(),
        );
    }

    #[Test]
    public function serverJsonSerialize(): void
    {
        $server = new Server(FrontendVhost::class, 'frontend', 1337, '', new Group(Type::Daemon, 'frontend'));

        self::assertSame(
            [
                'class' => FrontendVhost::class,
                'name' => 'frontend',
                'port' => 1337,
                'webrootPath' => '',
                'group' => [
                    'type' => 'daemon',
                    'name' => 'frontend',
                ],
            ],
            $server->jsonSerialize(),
        );
    }

    #[Test]
    public function handlerJsonSerialize(): void
    {
        $vhost   = new Vhost('healthz');
        $route   = new Route(HttpMethod::GET, '/');
        $handler = new Handler(IndexHandler::class, 'handle', true, [], $vhost, $route);

        self::assertSame(
            [
                'class' => IndexHandler::class,
                'method' => 'handle',
                'static' => true,
                'probeTypes' => [],
                'vhost' => $vhost,
                'route' => $route,
                'payload' => null,
            ],
            $handler->jsonSerialize(),
        );
    }

    #[Test]
    public function webSocketHandlerJsonSerialize(): void
    {
        $vhost   = new Vhost('frontend');
        $rpc     = new Rpc('ping');
        $handler = new WebSocketHandler(
            WebSocketPingHandler::class,
            'ping',
            false,
            $vhost,
            $rpc,
            WebSocketPingParams::class,
            WebSocketPingResult::class,
        );

        self::assertSame(
            [
                'class' => WebSocketPingHandler::class,
                'method' => 'ping',
                'static' => false,
                'vhost' => $vhost,
                'rpc' => $rpc,
                'paramsClass' => WebSocketPingParams::class,
                'returnClass' => WebSocketPingResult::class,
            ],
            $handler->jsonSerialize(),
        );
    }

    #[Test]
    public function webSocketChannelRegistrationJsonSerialize(): void
    {
        $vhost   = new Vhost('frontend');
        $channel = new Channel('demo-events', WebSocketDemoEvent::class);
        $item    = new WebSocketChannelRegistration($vhost, $channel, WebSocketDemoEvent::class);

        self::assertSame(
            [
                'vhost' => $vhost,
                'channel' => $channel,
                'payloadClass' => WebSocketDemoEvent::class,
            ],
            $item->jsonSerialize(),
        );
    }
}
