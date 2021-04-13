<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Generated;

use Mammatus\Http\Server\Web\Server;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

abstract class AbstractConfiguration
{
    /**
     * @return iterable<MiddlewareInterface>
     */
    abstract protected function middleware(): iterable;

    final protected function initialize(LoopInterface $loop, LoggerInterface $logger, ContainerInterface $container): void
    {
    }

    /**
     * @return iterable<Server>
     */
    public function servers(): iterable
    {
        yield from [];
    }
}
