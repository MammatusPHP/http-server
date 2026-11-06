<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\Http\Server\WebSocket\Connection;
use Ratchet\RFC6455\Messaging\Frame;

final class SuccessfulSendConnection extends Connection
{
    public function send(string|Frame $data): void
    {
    }
}
