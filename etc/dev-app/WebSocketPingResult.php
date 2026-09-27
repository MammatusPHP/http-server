<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

final readonly class WebSocketPingResult
{
    public function __construct(
        public bool $pong,
        public string $echo,
    ) {
    }
}
