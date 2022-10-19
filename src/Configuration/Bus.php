<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

final class Bus
{
    public readonly string $nameSanitized;

    /** @var array<Bus\Handler> */
    public readonly array $handlers;

    public function __construct(
        public readonly string $name,
        Bus\Handler ...$handlers
    ) {
        $this->nameSanitized = Sanitize::sanitize($this->name);
        $this->handlers      = $handlers;
    }
}
