<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server\WebSocket;

final readonly class PingResult
{
    public function __construct(
        public bool $pong,
        public string $echo,
    ) {
    }
}
