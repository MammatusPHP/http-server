<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

final class Rpc
{
    private string $name;

    private string $command;

    public function __construct(string $name, string $command)
    {
        $this->name    = $name;
        $this->command = $command;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommand(): string
    {
        return $this->command;
    }
}
