<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol\Rpc;

use JsonSerializable;
use Mammatus\Http\Server\WebSocket\Protocol\Operation;
use Mammatus\Http\Server\WebSocket\Protocol\WireProperties;

final readonly class Result implements JsonSerializable
{
    public function __construct(
        public string $id,
        public mixed $data,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            WireProperties::OPERATION->value => Operation::RESULT->value,
            WireProperties::ID->value        => $this->id,
            WireProperties::RESULT->value    => $this->data,
        ];
    }
}
