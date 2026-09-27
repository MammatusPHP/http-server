<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

final class WebSocketDefaults
{
    public const string HEARTBEAT_CHANNEL = 'heartbeat';

    public const float HEARTBEAT_INTERVAL_SECONDS = 13.0;
}
