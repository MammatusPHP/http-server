<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets\Result;

use Mammatus\Http\Server\WebSockets\Result;

final class Object implements Result
{
    private object $object;

    public function __construct(\JsonSerializable $object)
    {
        $this->object = $object;
    }

    public function jsonSerialize(): object
    {
        return $this->object;
    }
}
