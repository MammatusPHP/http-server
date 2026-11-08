<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\Http\Server\WebSocket\ResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use React\Http\Message\Response;
use WyriHaximus\TestUtilities\TestCase;

final class ResponseFactoryTest extends TestCase
{
    #[Test]
    public function createsReactHttpResponse(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createsResponseWithCustomStatus(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse(404, 'Not Found');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    #[Test]
    public function createsResponseWithDefaultReasonPhrase(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse(200);

        self::assertSame('OK', $response->getReasonPhrase());
    }

    #[Test]
    public function integerHeaderValuesAreCastToString(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        /** @phpstan-ignore argument.type */
        $result = $response->withHeader('X-Version', 13);

        self::assertSame(['13'], $result->getHeader('X-Version'));
    }

    #[Test]
    public function integerArrayHeaderValuesAreCastToString(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        /** @phpstan-ignore argument.type */
        $result = $response->withHeader('X-Multi', [1, 2, 3]);

        self::assertSame(['1', '2', '3'], $result->getHeader('X-Multi'));
    }

    #[Test]
    public function mixedArrayHeaderValuesAreCastToString(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        /** @phpstan-ignore argument.type */
        $result = $response->withHeader('X-Mixed', [1, 'two', 3]);

        self::assertSame(['1', 'two', '3'], $result->getHeader('X-Mixed'));
    }

    #[Test]
    public function withAddedHeaderCastsIntValueToString(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse()->withHeader('X-First', 'one');

        /** @phpstan-ignore argument.type */
        $result = $response->withAddedHeader('X-First', 2);

        self::assertSame(['one', '2'], $result->getHeader('X-First'));
    }

    #[Test]
    public function chainedWithHeaderCallsCastIntegers(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        $result = $response
            ->withHeader('Connection', 'Upgrade')
            ->withHeader('Upgrade', 'websocket')
            /** @phpstan-ignore argument.type */
            ->withHeader('Sec-WebSocket-Version', 13);

        self::assertSame(['Upgrade'], $result->getHeader('Connection'));
        self::assertSame(['websocket'], $result->getHeader('Upgrade'));
        self::assertSame(['13'], $result->getHeader('Sec-WebSocket-Version'));
    }

    #[Test]
    public function getHeaderLineReturnsString(): void
    {
        $factory = new ResponseFactory();
        /** @phpstan-ignore argument.type */
        $response = $factory->createResponse()->withHeader('X-Test', 42);

        self::assertSame('42', $response->getHeaderLine('X-Test'));
    }

    #[Test]
    public function hasHeaderReturnsBoolean(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse()->withHeader('X-Test', 'value');

        self::assertTrue($response->hasHeader('X-Test'));
        self::assertFalse($response->hasHeader('X-Missing'));
    }

    #[Test]
    public function getHeadersReturnsAllHeaders(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse()
            ->withHeader('X-One', 'one')
            /** @phpstan-ignore argument.type */
            ->withHeader('X-Two', 2);

        $headers = $response->getHeaders();

        self::assertSame(['one'], $headers['X-One']);
        self::assertSame(['2'], $headers['X-Two']);
    }

    #[Test]
    public function withStatusReturnsReactResponse(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        $result = $response->withStatus(404);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(404, $result->getStatusCode());
    }

    #[Test]
    public function withoutHeaderReturnsReactResponse(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse()->withHeader('X-Remove', 'value');

        $result = $response->withoutHeader('X-Remove');

        self::assertInstanceOf(Response::class, $result);
        /** @phpstan-ignore method.internalClass */
        self::assertSame([], $result->getHeader('X-Remove'));
    }

    #[Test]
    public function withProtocolVersionReturnsReactResponse(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        $result = $response->withProtocolVersion('2.0');

        self::assertInstanceOf(Response::class, $result);
        /** @phpstan-ignore method.internalClass */
        self::assertSame('2.0', $result->getProtocolVersion());
    }

    #[Test]
    public function getBodyReturnsEmptyStream(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        $body = $response->getBody();

        self::assertSame('', (string) $body);
    }

    #[Test]
    public function getProtocolVersionReturnsString(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse();

        self::assertSame('1.1', $response->getProtocolVersion());
    }

    #[Test]
    public function getReasonPhraseReturnsString(): void
    {
        $factory  = new ResponseFactory();
        $response = $factory->createResponse(200, 'OK');

        self::assertSame('OK', $response->getReasonPhrase());
    }
}
