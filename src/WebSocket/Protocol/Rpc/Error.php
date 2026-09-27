<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol\Rpc;

use JsonSerializable;
use Mammatus\Http\Server\WebSocket\Protocol\Operation;
use Mammatus\Http\Server\WebSocket\Protocol\WireProperties;

final readonly class Error implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $code,
        public string $message,
    ) {
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return [
            WireProperties::OPERATION->value => Operation::ERROR->value,
            WireProperties::ID->value        => $this->id,
            WireProperties::ERROR->value       => $this->code,
            WireProperties::MESSAGE->value     => $this->message,
        ];
    }
}
