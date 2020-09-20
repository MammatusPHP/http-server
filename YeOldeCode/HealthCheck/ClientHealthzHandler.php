<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\HealthCheck;

use Chimera\Mapping\Routing\FetchEndpoint;
use Mammatus\Http\Server\Annotations\Bus;
use Mammatus\Http\Server\Annotations\Vhost;
use Mammatus\Http\Server\Annotations\WebSocket\Realm;
use Mammatus\Http\Server\Annotations\WebSocket\Rpc;
use Mammatus\Http\Server\Annotations\WebSocket\Subscription;
use Psr\Log\LoggerInterface;

/**
 * @Vhost("healthz")
 * @Subscription(bus="healthz", realm="healthz", topic="client_healthz", command=ReceiveHealthz::class)
 */
final class ClientHealthzHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(ReceiveHealthz $healthz): void
    {
        $this->logger->notice('A client resports to be healthy with the following message "' . $healthz->message() . '", yay!');
    }
}
