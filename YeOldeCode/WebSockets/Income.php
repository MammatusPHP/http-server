<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

use ReactParallel\Pool\Worker\Work\Work;

final class Income implements Work
{
    private object $input;

    public function __construct(object $input)
    {
        $this->input = $input;
    }

    public function work(): object
    {
        return $this->input;
    }
}
