<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

/**
 * PSR-7 response factory that creates React HTTP responses.
 *
 * React HTTP's AbstractMessage casts header values to strings, so integer
 * headers from ratchet/rfc6455 are handled without error.
 */
final readonly class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], '', '1.1', $reasonPhrase);
    }
}
