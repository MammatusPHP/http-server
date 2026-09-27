<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend as FrontendHydrator;
use Mammatus\Http\Server\WebSocket\Rfc6455UpgradeMiddleware;
use Mammatus\Http\Server\WebSocket\WebSocketHub;
use Mammatus\Http\Server\WebSocket\WebSocketPayloadCodec;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function strtolower;

final class Rfc6455UpgradeMiddlewareTest extends TestCase
{
    #[Test]
    public function delegatesNonUpgradeRequests(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $response   = $middleware(
            new ServerRequest('GET', '/'),
            static fn (): Response => new Response(204),
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function rejectsIncompleteHandshake(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $request    = new ServerRequest('GET', '/')
            ->withHeader('Upgrade', 'websocket')
            ->withHeader('Sec-WebSocket-Version', '13');
        $response   = $middleware(
            $request,
            static function (): never {
                throw new RuntimeException('should not delegate');
            },
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function delegatesWhenWebSocketVersionHeaderMissing(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $response   = $middleware(
            new ServerRequest('GET', '/')->withHeader('Upgrade', 'websocket'),
            static fn (): Response => new Response(204),
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function delegatesWhenUpgradeHeaderMissingEvenWithVersion(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $response   = $middleware(
            new ServerRequest('GET', '/')->withHeader('Sec-WebSocket-Version', '13'),
            static fn (): Response => new Response(204),
        );

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function completesSuccessfulHandshake(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $request    = new ServerRequest('GET', 'http://localhost/ws', [
            'Host' => ['localhost'],
            'Upgrade' => ['websocket'],
            'Connection' => ['Upgrade'],
            'Sec-WebSocket-Key' => ['dGhlIHNhbXBsZSBub25jZQ=='],
            'Sec-WebSocket-Version' => ['13'],
        ]);
        $response   = $middleware(
            $request,
            static function (): never {
                throw new RuntimeException('should not delegate');
            },
        );

        self::assertSame(101, $response->getStatusCode());
        self::assertSame('websocket', strtolower($response->getHeaderLine('Upgrade')));
        self::assertNotSame('', $response->getHeaderLine('Sec-WebSocket-Accept'));
        self::assertNotSame('', $response->getHeaderLine('Connection'));
        self::assertSame('websocket', $response->getHeaderLine('Upgrade'));
        self::assertSame('', $response->getHeaderLine('Sec-WebSocket-Extensions'));
        $body = $response->getBody();
        /** @phpstan-ignore staticMethod.impossibleType */
        self::assertSame('React\Http\Io\HttpBodyStream', $body::class);
    }

    #[Test]
    public function doesNotNegotiatePerMessageDeflateWhenDisabled(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $request    = new ServerRequest('GET', 'http://localhost/ws', [
            'Host' => ['localhost'],
            'Upgrade' => ['websocket'],
            'Connection' => ['Upgrade'],
            'Sec-WebSocket-Key' => ['dGhlIHNhbXBsZSBub25jZQ=='],
            'Sec-WebSocket-Version' => ['13'],
            'Sec-WebSocket-Extensions' => ['permessage-deflate; client_max_window_bits'],
        ]);
        $response   = $middleware(
            $request,
            static function (): never {
                throw new RuntimeException('should not delegate');
            },
        );

        self::assertSame(101, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Sec-WebSocket-Extensions'));
    }

    #[Test]
    public function normalizeHeadersCollapsesSingleValueAndKeepsMultiValue(): void
    {
        $middleware = new Rfc6455UpgradeMiddleware($this->hub());
        $method     = new ReflectionMethod(Rfc6455UpgradeMiddleware::class, 'normalizeHeaders');
        $method->setAccessible(true);

        /** @var array<string, array<int, string>|string> $normalized */
        $normalized = $method->invoke($middleware, new Response(200, [
            'X-One' => ['solo'],
            'X-Two' => ['first', 'second'],
        ]));

        self::assertSame('solo', $normalized['X-One']);
        self::assertSame(['first', 'second'], $normalized['X-Two']);
    }

    private function hub(): WebSocketHub
    {
        return new WebSocketHub(new WebSocketPayloadCodec(new FrontendHydrator()), null);
    }
}
