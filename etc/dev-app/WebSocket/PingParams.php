<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server\WebSocket;

final readonly class PingParams
{
    public function __construct(
        public string $message,
    ) {
    }
}
