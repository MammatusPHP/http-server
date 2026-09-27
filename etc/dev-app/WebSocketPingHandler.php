<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc;
use Psr\Http\Message\ServerRequestInterface;

#[Vhost('frontend')]
final readonly class WebSocketPingHandler
{
    #[Rpc('ping')]
    public function ping(WebSocketPingParams $params, ServerRequestInterface $upgradeRequest): WebSocketPingResult
    {
        return new WebSocketPingResult(pong: true, echo: $params->message);
    }
}
