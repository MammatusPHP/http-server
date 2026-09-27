<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

/** @api */
interface WebSocketVhost
{
    public static function webSocketHeartbeatIntervalSeconds(): float|null;

    public static function webSocketServeClientAsset(): bool;
}
