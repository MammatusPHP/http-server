<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

final class Realm
{
    private string $name;

    private RealmAuth $auth;

    /** @var Rpc[] */
    private array $rpcs = [];

    /**
     * @param Rpc[] $rpcs
     */
    public function __construct(string $name, RealmAuth $auth, array $rpcs)
    {
        $this->name = $name;
        $this->auth = $auth;
        $this->rpcs = $rpcs;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAuth(): RealmAuth
    {
        return $this->auth;
    }

    /**
     * @return Rpc[]
     */
    public function getRpcs(): iterable
    {
        yield from $this->rpcs;
    }
}
