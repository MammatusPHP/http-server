<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Mammatus\DevApp\Http\Server\FrontendVhost;
use Mammatus\DevApp\Http\Server\WebSocket\DemoEvent;
use Mammatus\DevApp\Http\Server\WebSocket\PingHandler;
use Mammatus\DevApp\Http\Server\WebSocket\PingParams;
use Mammatus\DevApp\Http\Server\WebSocket\PingResult;
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
use Mammatus\Http\Server\Composer\WebSocket\ChannelRegistration;
use Mammatus\Http\Server\Composer\WebSocket\Handler as WebSocketHandler;
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
            PingHandler::class,
            'ping',
            false,
            $vhost,
            $rpc,
            PingParams::class,
            PingResult::class,
        );

        self::assertSame(
            [
                'class' => PingHandler::class,
                'method' => 'ping',
                'static' => false,
                'vhost' => $vhost,
                'rpc' => $rpc,
                'paramsClass' => PingParams::class,
                'returnClass' => PingResult::class,
            ],
            $handler->jsonSerialize(),
        );
    }

    #[Test]
    public function webSocketChannelRegistrationJsonSerialize(): void
    {
        $vhost   = new Vhost('frontend');
        $channel = new Channel('demo-events', DemoEvent::class);
        $item    = new ChannelRegistration($vhost, $channel, DemoEvent::class);

        self::assertSame(
            [
                'vhost' => $vhost,
                'channel' => $channel,
                'payloadClass' => DemoEvent::class,
            ],
            $item->jsonSerialize(),
        );
    }
}
