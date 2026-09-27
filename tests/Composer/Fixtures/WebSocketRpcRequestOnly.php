<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer\Fixtures;

use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc;
use Psr\Http\Message\ServerRequestInterface;

#[Vhost('fixture')]
final class WebSocketRpcRequestOnly
{
    #[Rpc('noop')]
    public function noop(ServerRequestInterface $request): string
    {
        return $request->getUri()->getPath();
    }
}
