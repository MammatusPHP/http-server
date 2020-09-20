<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

final class RealmAuth
{
    private bool $enable;

    private string $key;

    public function __construct(bool $enable, string $key)
    {
        $this->enable = $enable;
        $this->key    = $key;
    }

    public function isEnabled(): bool
    {
        return $this->enable;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
