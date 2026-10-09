<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Channel;

#[Vhost('frontend')]
#[Channel('demo-events', WebSocketDemoEvent::class)]
final readonly class WebSocketDemoEvent
{
    public function __construct(
        public string $message,
    ) {
    }
}
