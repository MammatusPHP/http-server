<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol\PubSub;

use JsonSerializable;
use Mammatus\Http\Server\WebSocket\Protocol\Operation;
use Mammatus\Http\Server\WebSocket\Protocol\WireProperties;

final readonly class Publish implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $channel,
        public mixed $data,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $encoded = [
            WireProperties::OPERATION->value => Operation::PUBLISH->value,
            WireProperties::CHANNEL->value   => $this->channel,
            WireProperties::DATA->value      => $this->data,
        ];

        if ($this->id !== '') {
            $encoded[WireProperties::ID->value] = $this->id;
        }

        return $encoded;
    }
}
