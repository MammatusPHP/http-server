<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Message\Response;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;
use Throwable;

use function count;

final readonly class Rfc6455UpgradeMiddleware
{
    public function __construct(
        private WebSocketHub $hub,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        if (! $this->isWebSocketRequest($request)) {
            /** @var ResponseInterface $response */
            $response = $next($request);

            return $response;
        }

        $negotiator = new ServerNegotiator(new RequestVerifier(), new HttpFactory(), false);
        $response   = $negotiator->handshake($request);
        if ($response->getStatusCode() !== 101) {
            return $response;
        }

        try {
            $permessageDeflateOptions = PermessageDeflateOptions::fromRequestOrResponse($response)[0];
        } catch (Throwable $throwable) {
            return Response::plaintext('WebSocket negotiation failed: ' . $throwable->getMessage())->withStatus(500);
        }

        $inStream  = new ThroughStream();
        $outStream = new ThroughStream();

        $reactResponse = new Response(
            101,
            $this->normalizeHeaders($response),
            new CompositeStream($outStream, $inStream),
        );

        $connection = new Connection(
            new CompositeStream($inStream, $outStream),
            $permessageDeflateOptions,
        );

        $this->hub->onConnect($connection, $request);

        return $reactResponse;
    }

    private function isWebSocketRequest(ServerRequestInterface $request): bool
    {
        $upgrade = $request->getHeader('Upgrade');
        if ($upgrade === []) {
            return false;
        }

        $version = $request->getHeader('Sec-WebSocket-Version');

        return $version !== [];
    }

    /** @return array<string, array<string>|string> */
    private function normalizeHeaders(ResponseInterface $response): array
    {
        $normalized = [];
        foreach ($response->getHeaders() as $name => $values) {
            $normalized[$name] = count($values) === 1 ? $values[0] : $values;
        }

        /** @var array<string, array<string>|string> $headers */
        $headers = $normalized;

        return $headers;
    }
}
