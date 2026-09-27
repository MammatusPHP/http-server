<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

final readonly class WebSocketPingParams
{
    public function __construct(
        public string $message,
    ) {
    }
}
