<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol\PubSub;

use JsonSerializable;
use Mammatus\Http\Server\WebSocket\Protocol\Operation;
use Mammatus\Http\Server\WebSocket\Protocol\WireProperties;

final readonly class Unsubscribe implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $channel,
    ) {
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        $encoded = [
            WireProperties::OPERATION->value => Operation::UNSUBSCRIBE->value,
            WireProperties::CHANNEL->value   => $this->channel,
        ];

        if ($this->id !== '') {
            $encoded[WireProperties::ID->value] = $this->id;
        }

        return $encoded;
    }
}
