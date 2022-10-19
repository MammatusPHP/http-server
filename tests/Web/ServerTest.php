<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Web;

use Mammatus\Http\Server\Web\Server;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Http\Message\Response;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

use function React\Async\await;

final class ServerTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function functional(): void
    {
        $address = '0.0.0.0:41385';
        $url     = 'http://' . $address . '/';
        $called  = false;
        $server  = new Server('name', $address, new HttpServer(static function () use (&$called): ResponseInterface {
            /** @phpstan-ignore-next-line */
            return new Response(Response::STATUS_OK, ['called' => ($called = true)]);
        }));
        $browser = new Browser();

        $server->start();
        await($browser->get($url));
        $server->stop();
        self::assertTrue($called);
    }
}
