<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

use Thruway\ClientSession;

final class Broadcaster
{
    private ClientSession $session;

    public function __construct(ClientSession $session)
    {
        $this->session = $session;
    }

    public function broadcast(string $topic, array $data): void
    {
        $this->session->publish($topic, [$data]);
    }
}
