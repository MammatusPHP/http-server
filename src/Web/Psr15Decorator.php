<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Web;

use FriendsOfReact\Http\Middleware\Psr15Adapter\PSR15Middleware;
use Psr\Http\Server\MiddlewareInterface;

final class Psr15Decorator
{
    public static function decorate(MiddlewareInterface|callable ...$middleware): iterable
    {
        foreach ($middleware as $handler) {
            if ($handler instanceof MiddlewareInterface) {
                $handler = new PSR15Middleware($handler);
            }

            yield $handler;
        }
    }
}
