<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Mammatus\Http\Server\WebSocket\ClientAsset;
use Mammatus\Http\Server\WebSocket\Middleware\WebSocketClientAssetMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

final class WebSocketClientAssetMiddlewareTest extends TestCase
{
    private const string SAMPLE = 'export class MammatusWebSocket {}';

    #[Test]
    public function servesClientModule(): void
    {
        $middleware = new WebSocketClientAssetMiddleware(self::SAMPLE);
        $response   = $middleware->process(
            new ServerRequest('GET', ClientAsset::PATH),
            new readonly class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new RuntimeException('should not delegate');
                }
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/javascript', $response->getHeaderLine('Content-Type'));
        self::assertSame(self::SAMPLE, (string) $response->getBody());
    }

    #[Test]
    public function delegatesOtherPaths(): void
    {
        $middleware = new WebSocketClientAssetMiddleware(self::SAMPLE);
        $response   = $middleware->process(
            new ServerRequest('GET', '/elsewhere'),
            new readonly class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(418);
                }
            },
        );

        self::assertSame(418, $response->getStatusCode());
    }
}
