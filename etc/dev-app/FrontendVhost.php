<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Groups\Attributes\Group;
use Mammatus\Groups\Type;
use Mammatus\Http\Server\Configuration\Vhost;
use Mammatus\Http\Server\Configuration\Webroot;
use Mammatus\Http\Server\Webroot\NoWebroot;
use Psr\Http\Server\MiddlewareInterface;

#[Group(Type::Daemon, 'frontend')]
final class FrontendVhost implements Vhost
{
    private const string SERVER_NAME = 'frontend';
    private const int LISTEN_PORT    = 1337;

    public static function port(): int
    {
        return self::LISTEN_PORT;
    }

    public static function name(): string
    {
        return self::SERVER_NAME;
    }

    public static function webroot(): Webroot
    {
        return new NoWebroot();
    }

    public static function maxConcurrentRequests(): null
    {
        return null;
    }

    /** @return iterable<MiddlewareInterface> */
    public function middleware(): iterable
    {
        yield from [];
    }
}
