<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets\Result;

use Mammatus\Http\Server\WebSockets\Result;

final class String_ implements Result
{
    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function jsonSerialize(): string
    {
        return $this->string;
    }
}
