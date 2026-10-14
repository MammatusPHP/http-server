<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\Http\Server\WebSocket\Connection;
use Ratchet\RFC6455\Messaging\Frame;

use function is_string;

final class RecordingConnection extends Connection
{
    /** @var list<string> */
    private array $sent = [];

    public function send(string|Frame $data): void
    {
        if (is_string($data)) {
            $this->sent[] = $data;
        }

        parent::send($data);
    }

    /** @return list<string> */
    public function sent(): array
    {
        return $this->sent;
    }
}
