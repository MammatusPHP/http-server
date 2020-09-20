<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\HealthCheck;

use Mammatus\Http\Server\Annotations\Vhost;
use Mammatus\Http\Server\Annotations\WebSocket\Broadcast;
use Mammatus\Http\Server\WebSockets\Broadcast as BroadcastContract;
use Mammatus\Http\Server\WebSockets\Broadcaster;
use Mammatus\Http\Server\WebSockets\Result\Array_;
use React\EventLoop\LoopInterface;

/**
 * @Vhost("healthz")
 * @Broadcast(realm="healthz")
 */
final class HealthBroadcast implements BroadcastContract
{
    private LoopInterface $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function broadcast(Broadcaster $broadcaster)
    {
        $this->loop->addPeriodicTimer(13, static function () use ($broadcaster) {
            $broadcaster->broadcast('healthz', ['status' => 'healthy']);
        });
    }
}
