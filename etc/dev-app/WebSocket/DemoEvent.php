<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server\WebSocket;

use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Channel;

#[Vhost('frontend')]
#[Channel('demo-events', DemoEvent::class)]
final readonly class DemoEvent
{
    public function __construct(
        public string $message,
    ) {
    }
}
