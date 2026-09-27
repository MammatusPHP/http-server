<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use GuzzleHttp\Psr7\ServerRequest;
use Mammatus\Http\Server\WebSocket\ClientAsset;
use Mammatus\Http\Server\WebSocket\Middleware\ClientAssetMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;

final class ClientAssetDiTest extends TestCase
{
    #[Test]
    public function diFactoryLoadsClientModule(): void
    {
        /** @var array<class-string, callable(): ClientAssetMiddleware> $definitions */
        $definitions = require dirname(__DIR__, 2) . '/etc/di/websocket.php';
        $middleware  = $definitions[ClientAssetMiddleware::class]();
        $response    = $middleware->process(
            new ServerRequest('GET', ClientAsset::PATH),
            new readonly class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new RuntimeException('should not delegate');
                }
            },
        );

        $body = (string) $response->getBody();
        self::assertStringContainsString('MammatusWebSocket', $body);
        self::assertStringContainsString("export const HEARTBEAT_CHANNEL = 'heartbeat'", $body);
        self::assertStringNotContainsString('%%MAMMATUS_WEBSOCKET_HEARTBEAT_CHANNEL%%', $body);
        self::assertStringContainsString('#resubscribeAll()', $body);
        self::assertStringContainsString('disconnect()', $body);
        self::assertStringContainsString('#scheduleReconnect', $body);
        self::assertStringContainsString("CALL: 'rpc'", $body);
        self::assertStringNotContainsString('%%MAMMATUS_WEBSOCKET_OPERATION_CALL%%', $body);
        self::assertStringContainsString('[WireProperties.OPERATION]: Operation.SUBSCRIBE', $body);
        self::assertStringContainsString("CHANNEL: 'c'", $body);
        self::assertStringNotContainsString('%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_OPERATION%%', $body);
        self::assertStringContainsString('[WireProperties.CHANNEL]: channel', $body);
    }
}
