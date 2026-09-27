<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol\Rpc;

use JsonSerializable;
use Mammatus\Http\Server\WebSocket\Protocol\Operation;
use Mammatus\Http\Server\WebSocket\Protocol\WireProperties;

final readonly class Call implements JsonSerializable
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $method,
        public array $payload,
    ) {
    }

    /** @return array<string, string|array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return [
            WireProperties::OPERATION->value => Operation::CALL->value,
            WireProperties::ID->value        => $this->id,
            WireProperties::METHOD->value    => $this->method,
            WireProperties::PAYLOAD->value   => $this->payload,
        ];
    }
}
