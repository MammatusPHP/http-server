<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Middleware;

use Mammatus\Http\Server\WebSocket\ClientAsset;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;

final readonly class WebSocketClientAssetMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $javaScriptModuleContents,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || $request->getUri()->getPath() !== ClientAsset::PATH) {
            return $handler->handle($request);
        }

        return new Response(200, ['Content-Type' => 'text/javascript'], $this->javaScriptModuleContents);
    }
}
