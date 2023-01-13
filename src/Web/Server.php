<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Web;

use React\Http\HttpServer;
use React\Socket\SocketServer;

final class Server
{
    private string $address;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ?SocketServer $socket = null;
    private HttpServer $http;

    public function __construct(public readonly string $name, string $address, HttpServer $http)
    {
        $this->address = $address;
        $this->http    = $http;
    }

    public function start(): void
    {
        $this->socket = new SocketServer($this->address);
        $this->http->listen($this->socket);
    }

    public function stop(): void
    {
        if (! ($this->socket instanceof SocketServer)) {
            return;
        }

        $this->socket->close();
    }
}
