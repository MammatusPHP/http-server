<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration\WebSocket;

use Mammatus\Http\Server\Configuration\Sanitize;

final class Broadcast
{
    public readonly string $classSanitized;

    public function __construct(
        public readonly string $class
    ) {
        $this->classSanitized = Sanitize::sanitize($this->class);
    }
}
