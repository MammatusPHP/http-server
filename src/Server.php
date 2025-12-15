<?php

declare(strict_types=1);

namespace Mammatus\Http\Server;

use Mammatus\Groups\Contracts\LifeCycleHandler;
use Mammatus\Http\Server\Generated\AbstractServer;
use Mammatus\LifeCycleEvents\Boot;
use Mammatus\LifeCycleEvents\Shutdown;
use Mammatus\LifeCycleEvents\Start;
use Psr\Container\ContainerInterface;
use WyriHaximus\Broadcast\Contracts\Listener;

final class Server extends AbstractServer implements Listener
{
    /** @var list<LifeCycleHandler> */
    private array $runningServers = [];

    /** @phpstan-ignore ergebnis.noParameterWithContainerTypeDeclaration */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function start(Boot|Start $event): void
    {
        foreach ($this->servers() as $serverClass) {
            $server = $this->container->get($serverClass);
            if (! $server instanceof LifeCycleHandler) {
                continue;
            }

            $server->start();
            $this->runningServers[] = $server;
        }
    }

    public function stop(Shutdown $event): void
    {
        foreach ($this->runningServers as $server) {
            $server->stop();
        }
    }
}
