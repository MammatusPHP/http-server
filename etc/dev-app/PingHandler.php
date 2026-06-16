<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Http\Server\Attributes\HttpMethod;
use Mammatus\Http\Server\Attributes\Route;
use Mammatus\Http\Server\Attributes\Vhost;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\Http\Message\Response;

use const WyriHaximus\Constants\HTTPStatusCodes\OK;

#[Vhost('frontend')]
#[Route(HttpMethod::GET, '/ping/{name}')]
final readonly class PingHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Ping $ping): ResponseInterface
    {
        $this->logger->info('Ping received: {ping}', ['event' => $ping->name]);

        return new Response(
            OK,
            ['Content-Type' => 'application/json'],
            '{}',
        );
    }
}
