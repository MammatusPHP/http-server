<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration\Bus;

final class Handler
{
    public function __construct(
        public readonly string $bus,
        public readonly string $command,
        public readonly string $handler,
    ) {
    }
}
