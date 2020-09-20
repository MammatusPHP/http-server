<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

use ReactParallel\Pool\Worker\Work\Result as WorkResult;

final class Outcome implements WorkResult
{
    private Result $result;

    public function __construct(Result $result)
    {
        $this->result = $result;
    }

    public function result(): Result
    {
        return $this->result;
    }
}
