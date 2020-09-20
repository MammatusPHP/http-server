<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\HealthCheck;

use Ancarda\Psr7\StringStream\ReadOnlyStringStream;
use Laminas\Diactoros\Response;
use Mammatus\Http\Server\CommandBus\Result;
use Mammatus\Http\Server\WebSockets\Result as WebSocketResult;
use Psr\Http\Message\ResponseInterface;

use function Safe\json_encode;

final class HealthResult implements Result
{
    private string $result;

    public function __construct(string $result)
    {
        $this->result = $result;
    }

    public function response(): ResponseInterface
    {
        return new Response(new ReadOnlyStringStream(json_encode(['result' => $this->result])), 200, ['Content-Type' => 'text/plain']);
    }

    public function result(): WebSocketResult
    {
        return new WebSocketResult\Array_([
            'result' => $this->result,
        ]);
    }
}
