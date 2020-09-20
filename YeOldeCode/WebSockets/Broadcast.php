<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

interface Broadcast
{
    public function broadcast(Broadcaster $broadcaster);
}
