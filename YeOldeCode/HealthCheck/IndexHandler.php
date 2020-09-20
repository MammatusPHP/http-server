<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\HealthCheck;

use Chimera\Mapping\Routing\FetchEndpoint;
use Laminas\Diactoros\Response;
use Mammatus\Http\Server\Annotations\Bus;
use Mammatus\Http\Server\Annotations\Vhost;
use Psr\Http\Message\ResponseInterface;

/**
 * @Vhost("healthz")
 * @FetchEndpoint(app="healthz", path="/", query=FetchIndex::class, name="FetchIndex")
 */
final class IndexHandler
{
    public function handle(FetchIndex $request): ResponseInterface
    {
        return new Response('Shoo!', 308, ['Location' => '/index.html']);
    }
}
