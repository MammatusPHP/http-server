<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration\WebSocket;

use Mammatus\Http\Server\Configuration\Sanitize;

final class Rpc
{
    public readonly string $nameSanitized;

    public function __construct(
        public readonly string $name,
        public readonly string $command,
        public readonly string $bus,
        public readonly ?string $transformer
    ) {
        $this->nameSanitized = Sanitize::sanitize($this->name);
    }
}
