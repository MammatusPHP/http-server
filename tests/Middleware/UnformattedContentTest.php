<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Middleware;

use Lcobucci\ContentNegotiation\UnformattedResponse;
use Mammatus\Http\Server\Middleware\UnformattedContent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\PromiseInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function assert;
use function React\Async\await;
use function React\Promise\resolve;

final class UnformattedContentTest extends AsyncTestCase
{
    /**
     * @return iterable<string, array<callable(ResponseInterface): ResponseInterface|callable(ResponseInterface): PromiseInterface>>
     */
    public function promoiseOrNotToPromiseProvider(): iterable
    {
        yield 'promise' => [static fn (ResponseInterface $response): PromiseInterface => resolve($response)];
        yield 'no-promise' => [static fn (ResponseInterface $response): ResponseInterface => $response];
    }

    /**
     * @param (callable(ResponseInterface): ResponseInterface|callable(ResponseInterface): PromiseInterface) $pontp
     *
     * @dataProvider promoiseOrNotToPromiseProvider
     * @test
     */
    public function response(callable $pontp): void
    {
        $content             = 'blaat';
        $response            = new Response(Response::STATUS_OK, [], $content);
        $unformattedResponse = await((new UnformattedContent())(new ServerRequest('GET', 'https://example.com/'), static fn (ServerRequestInterface $request): ResponseInterface|PromiseInterface => $pontp($response)));
        assert($unformattedResponse instanceof ResponseInterface);

        self::assertSame($content, (string) $unformattedResponse->getBody());
    }

    /**
     * @dataProvider promoiseOrNotToPromiseProvider
     * @test
     */
    public function unformattedResponseString(callable $pontp): void
    {
        $content             = 'blaat';
        $response            = new UnformattedResponse(new Response(), $content);
        $unformattedResponse = await((new UnformattedContent())(new ServerRequest('GET', 'https://example.com/'), static fn (ServerRequestInterface $request): ResponseInterface|PromiseInterface => $pontp($response)));
        assert($unformattedResponse instanceof UnformattedResponse);

        self::assertSame($content, $unformattedResponse->getUnformattedContent());
    }

    /**
     * @dataProvider promoiseOrNotToPromiseProvider
     * @test
     */
    public function unformattedResponseResponse(callable $pontp): void
    {
        $content             = new Response(Response::STATUS_OK, [], 'blaat');
        $response            = new UnformattedResponse(new Response(), $content);
        $unformattedResponse = await((new UnformattedContent())(new ServerRequest('GET', 'https://example.com/'), static fn (ServerRequestInterface $request): ResponseInterface|PromiseInterface => $pontp($response)));

        self::assertSame($content, $unformattedResponse);
    }
}
