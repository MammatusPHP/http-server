<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSockets;

use League\Tactician\Middleware;
use Mammatus\Http\Server\CommandBus\Result as CommandBusResult;
use Mammatus\Http\Server\WebSockets\Result\String_;
use Psr\Http\Message\ResponseInterface;

final class ResponseTransformerMiddleware implements Middleware
{
    public function execute($command, callable $next): ?Result
    {
        $result = $next($command);

        return $this->extractResult($result);
    }

    private function extractResult(object $result): ?Result
    {
        if ($result instanceof ResponseInterface) {
            return new String_((string)$result->getBody());
        }

        if ($result instanceof CommandBusResult) {
            return $result->result();
        }

        return $result;
    }
}
