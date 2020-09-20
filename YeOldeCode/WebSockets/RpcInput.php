<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

use Chimera\Input;

final class RpcInput implements Input
{
    private ?object $data;

    public function __construct(?object $data)
    {
        $this->data = $data;
    }

    public function getAttribute(string $name, $default = null)
    {
        return null;
    }

    public function data(): ?object
    {
        return $this->data;
    }

    /**
     * @deprecated
     */
    public function getData(): array
    {
        return [$this->data];
    }
}
