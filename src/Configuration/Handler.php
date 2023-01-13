<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

final class Handler
{
    public readonly string $busSanitized;

    /**
     * @param array<string> $methods
     */
    public function __construct(
        /** @var array<string>  */
        public readonly array $methods,
        public readonly string $bus,
        public readonly string $command,
        public readonly string $commandHandler,
        public readonly string $handler,
        public readonly string $path,
    ) {
        $this->busSanitized = Sanitize::sanitize($this->bus);
    }
}
