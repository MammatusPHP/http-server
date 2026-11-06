<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server\WebSocket;

use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc;
use Psr\Http\Message\ServerRequestInterface;

#[Vhost('frontend')]
final readonly class PingHandler
{
    #[Rpc('ping')]
    public function ping(PingParams $params, ServerRequestInterface $upgradeRequest): PingResult
    {
        return new PingResult(pong: true, echo: $params->message);
    }
}
