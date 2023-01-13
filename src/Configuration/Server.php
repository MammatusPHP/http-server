<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

use Mammatus\Http\Server\Configuration\WebSocket\Realm;
use Mammatus\Http\Server\Webroot\WebrootPath;

use function array_filter;
use function assert;
use function count;

final class Server
{
    /**
     * @param array<Realm>   $realms
     * @param array<Handler> $handlers
     * @param array<Bus>     $busses
     */
    public function __construct(
        public readonly VhostStub $vhost,
        public readonly array $realms,
        public readonly array $handlers,
        public readonly array $busses,
    ) {
    }

    public function hasRpcs(): bool
    {
        return count($this->realms) > count(array_filter($this->realms, static fn (Realm $realm): bool => count($realm->rpcs) === 0));
    }

    public function hasSubscriptions(): bool
    {
        return count($this->realms) > count(array_filter($this->realms, static fn (Realm $realm): bool => count($realm->subscriptions) === 0));
    }

    public function hasWebroot(): bool
    {
        return $this->vhost->webroot instanceof WebrootPath;
    }

    public function webroot(): string
    {
        assert($this->vhost->webroot instanceof WebrootPath);

        return $this->vhost->webroot->path();
    }
}
