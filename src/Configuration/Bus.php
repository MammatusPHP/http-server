<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

final class Bus
{
    private string $name;

    /** @var array<Bus\Handler> */
    private array $handlers = [];

    /**
     * @param array<Bus\Handler> $handlers
     */
    public function __construct(string $name, Bus\Handler ...$handlers)
    {
        $this->name     = $name;
        $this->handlers = $handlers;
    }

    public function nameSanitized(): string
    {
        return Sanitize::sanitize($this->name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function handlers(): array
    {
        return $this->handlers;
    }
}
