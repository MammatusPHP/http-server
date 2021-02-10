<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration\WebSocket;

use function assert;

final class Rpc
{
    private string $name;
    private string $command;
    private string $bus;
    private ?string $transformer;

    public function __construct(string $name, string $command, string $bus, ?string $transformer)
    {
        $this->name = $name;
        $this->command = $command;
        $this->bus = $bus;
        $this->transformer = $transformer;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function command(): string
    {
        return $this->command;
    }

    public function bus(): string
    {
        return $this->bus;
    }

    public function transformer(): ?string
    {
        return $this->transformer;
    }
}
