<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer\Fixtures;

use Mammatus\DevApp\Http\Server\WebSocket\PingParams;
use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc;
use Psr\Http\Message\ServerRequestInterface;

#[Vhost('fixture')]
final class WebSocketRpcUnionReturn
{
    #[Rpc('union')]
    public function union(PingParams $params, ServerRequestInterface $request): string|int
    {
        return $params->message === '' ? 0 : $params->message;
    }
}
