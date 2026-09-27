<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\Http\Server\WebSocket\Connection;
use Ratchet\RFC6455\Messaging\Frame;
use RuntimeException;

final class FailingSendConnection extends Connection
{
    public function send(string|Frame $data): void
    {
        throw new RuntimeException('send failed');
    }
}
