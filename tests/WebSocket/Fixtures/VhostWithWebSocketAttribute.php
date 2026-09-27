<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket\Fixtures;

use Mammatus\Http\Server\Attributes\WebSocket\HeartbeatChannel;
use Mammatus\Http\Server\Attributes\WebSocket\HeartbeatInterval;
use Mammatus\Http\Server\Attributes\WebSocket\ServeClientAsset;

#[HeartbeatInterval(seconds: 9.0)]
#[HeartbeatChannel('custom-heartbeat')]
#[ServeClientAsset]
final class VhostWithWebSocketAttribute
{
}
