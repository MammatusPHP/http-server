<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use JsonSerializable;
use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Channel;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class WebSocketChannelRegistration implements ItemContract, JsonSerializable
{
    /** @param class-string $payloadClass */
    public function __construct(
        public Vhost $vhost,
        public Channel $channel,
        public string $payloadClass,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'vhost' => $this->vhost,
            'channel' => $this->channel,
            'payloadClass' => $this->payloadClass,
        ];
    }
}
