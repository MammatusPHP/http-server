<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Web;

use League\Tactician\Middleware;
use Mammatus\Http\Server\CommandBus\Result as CommandBusResult;
use Psr\Http\Message\ResponseInterface;

final class ResponseTransformerMiddleware implements Middleware
{
    public function execute($command, callable $next): ResponseInterface // phpcs:disable
    {
        return $this->extractResult($next($command));
    }

    private function extractResult(CommandBusResult|ResponseInterface $result): ResponseInterface
    {
        if ($result instanceof CommandBusResult) {
            return $result->response();
        }

        return $result;
    }
}
