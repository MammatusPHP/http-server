<?php

declare(strict_types=1);

namespace Mammatus\Http\Server;

use Mammatus\LifeCycleEvents\Initialize;
use Mammatus\LifeCycleEvents\Shutdown;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use WyriHaximus\Broadcast\Contracts\Listener;

final class Server implements Listener
{
    private Configuration $configuration;
    private LoopInterface $loop;
    private LoggerInterface $logger;

    public function __construct(Configuration $configuration, LoopInterface $loop, LoggerInterface $logger)
    {
        $this->configuration = $configuration;
        $this->loop          = $loop;
        $this->logger        = $logger;
    }

    public function start(Initialize $event): void
    {
        foreach ($this->configuration->servers() as $server) {
            $this->logger->debug('Starting server: ' . $server->name());
            $server->start($this->loop);
            $this->logger->debug('Started server: ' . $server->name());
        }
    }

    public function stop(Shutdown $event): void
    {
        foreach ($this->configuration->servers() as $server) {
            $this->logger->debug('Stopping server: ' . $server->name());
            $server->stop();
            $this->logger->debug('Stopped server: ' . $server->name());
        }
    }
}
