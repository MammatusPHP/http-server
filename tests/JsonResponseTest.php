<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server;

use Mammatus\Http\Server\JsonResponse;
use React\EventLoop\Loop;
use React\Stream\ReadableStreamInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\JsonStream;

use function React\Async\await;
use function React\Promise\Stream\buffer;
use function Safe\json_encode;
use function time;

final class JsonResponseTest extends AsyncTestCase
{
    public function testDataTransfer(): void
    {
        $data = [
            'bool' => [
                false,
                true,
            ],
            'string' => 'beer',
            'int' => time(),
        ];

        $jsonStream = new JsonStream();

        Loop::addTimer(0.1, static function () use ($jsonStream, $data): void {
            $jsonStream->end($data);
        });

        $response = JsonResponse::create($jsonStream, 666, [], '1.1', 'oops');

        self::assertSame('oops', $response->getReasonPhrase());

        $body = $response->getBody();
        self::assertInstanceOf(ReadableStreamInterface::class, $body);

        $body = await(buffer($body));
        self::assertSame(json_encode($data), $body);
    }
}
