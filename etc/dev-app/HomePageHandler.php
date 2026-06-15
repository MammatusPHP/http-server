<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Http\Server\Attributes\HttpMethod;
use Mammatus\Http\Server\Attributes\Route;
use Mammatus\Http\Server\Attributes\Vhost;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

use const WyriHaximus\Constants\HTTPStatusCodes\OK;

#[Vhost('frontend')]
#[Route(HttpMethod::GET, '/')]
final readonly class HomePageHandler
{
    public function handle(): ResponseInterface
    {
        return new Response(
            OK,
            ['Content-Type' => 'text/plain'],
            'Hello World!',
        );
    }
}
