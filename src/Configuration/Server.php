<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

use Mammatus\Http\Server\Webroot\WebrootPath;
use function assert;

final class Server
{
    private Vhost $vhost;
    /** @var array<\Mammatus\Http\Server\Configuration\WebSocket\Realm>  */
    private array $realms;
    /** @var array<Handler>  */
    private array $handlers;
    /** @var array<Bus>  */
    private array $busses;

    /**
     * @param array<\Mammatus\Http\Server\Configuration\WebSocket\Realm> $realms
     * @param array<Handler> $handlers
     * @param array<Bus> $busses
     */
    public function __construct(Vhost $vhost, array $realms, array $handlers, array $busses)
    {
        $this->vhost  = $vhost;
        $this->realms = $realms;
        $this->handlers = $handlers;
        $this->busses = $busses;
    }

    /**
     * @return iterable<Handler>
     */
    public function handlers(): iterable
    {
        yield from $this->handlers;
    }

    /**
     * @return iterable<Bus>
     */
    public function busses(): iterable
    {
        yield from $this->busses;
    }

    /**
     * @return iterable<\Mammatus\Http\Server\Configuration\WebSocket\Realm>
     */
    public function realms(): iterable
    {
        yield from $this->realms;
    }

    public function vhost(): Vhost
    {
        return $this->vhost;
    }

    public function vhostClass(): string
    {
        return get_class($this->vhost);
    }

    public function hasWebroot(): bool
    {
        return $this->vhost->webroot() instanceof WebrootPath;
    }

    public function webroot(): string
    {
        assert($this->vhost->webroot() instanceof WebrootPath);

        return $this->vhost->webroot()->path();
    }
}
