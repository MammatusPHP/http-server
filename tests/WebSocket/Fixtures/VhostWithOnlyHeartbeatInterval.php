<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket\Fixtures;

use Mammatus\Http\Server\Attributes\WebSocket\HeartbeatInterval;

#[HeartbeatInterval(seconds: 5.0)]
final class VhostWithOnlyHeartbeatInterval
{
}
