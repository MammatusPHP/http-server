<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\HealthCheck;

use Chimera\Input;

final class FetchHealthz
{
    public static function fromInput(Input $input): FetchHealthz
    {
        return new self();
    }
}
