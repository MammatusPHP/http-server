<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

use Mammatus\Http\Server\Webroot\WebrootPath;
use function assert;

final class VhostStub
{
    private string $class;
    private int $port;
    private string $name;
    private Webroot $webroot;
    private ?int $maxConcurrentRequests;

    public function __construct(string $class, int $port, string $name, Webroot $webroot, ?int $maxConcurrentRequests)
    {
        $this->class = $class;
        $this->port = $port;
        $this->name = $name;
        $this->webroot = $webroot;
        $this->maxConcurrentRequests = $maxConcurrentRequests;
    }

    public function class(): string
    {
        return $this->class;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function webroot(): Webroot
    {
        return $this->webroot;
    }

    public function maxConcurrentRequests(): ?int
    {
        return $this->maxConcurrentRequests;
    }
}
