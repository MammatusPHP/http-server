<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use Mammatus\Http\Server\Attributes\WebSocket\HeartbeatChannel;
use Mammatus\Http\Server\Attributes\WebSocket\HeartbeatInterval;
use Mammatus\Http\Server\Attributes\WebSocket\ServeClientAsset;
use ReflectionClass;

final readonly class WebSocketVhostConfiguration
{
    public function __construct(
        public float|null $heartbeatIntervalSeconds,
        public string $heartbeatChannel,
        public bool $serveClientAsset,
    ) {
    }

    /** @param class-string $vhostClass */
    public static function fromVhostClass(string $vhostClass): self
    {
        $reflection = new ReflectionClass($vhostClass);

        return new self(
            self::resolveHeartbeatIntervalSeconds($reflection),
            self::resolveHeartbeatChannel($reflection),
            $reflection->getAttributes(ServeClientAsset::class) !== [],
        );
    }

    /** @param ReflectionClass<object> $reflection */
    private static function resolveHeartbeatIntervalSeconds(ReflectionClass $reflection): float|null
    {
        $intervalAttributes = $reflection->getAttributes(HeartbeatInterval::class);
        if ($intervalAttributes === []) {
            return WebSocketDefaults::HEARTBEAT_INTERVAL_SECONDS;
        }

        $seconds = $intervalAttributes[0]->newInstance()->seconds;
        if ($seconds <= 0.0) {
            return null;
        }

        return $seconds;
    }

    /** @param ReflectionClass<object> $reflection */
    private static function resolveHeartbeatChannel(ReflectionClass $reflection): string
    {
        $channelAttributes = $reflection->getAttributes(HeartbeatChannel::class);
        if ($channelAttributes === []) {
            return WebSocketDefaults::HEARTBEAT_CHANNEL;
        }

        return $channelAttributes[0]->newInstance()->channel;
    }
}
