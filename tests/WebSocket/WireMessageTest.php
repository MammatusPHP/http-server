<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use JsonException;
use Mammatus\Http\Server\WebSocket\Protocol\WireMessage;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class WireMessageTest extends TestCase
{
    #[Test]
    public function encodeAndDecode(): void
    {
        $json    = WireMessage::encode('rpc', ['i' => '1', 'm' => 'ping']);
        $message = WireMessage::decode($json);

        self::assertSame('rpc', $message->op);
        self::assertSame('1', $message->string('i'));
        self::assertSame('ping', $message->string('m'));
    }

    #[Test]
    public function decodeRejectsMissingOp(): void
    {
        $this->expectException(JsonException::class);
        WireMessage::decode('{"x":1}');
    }

    #[Test]
    public function arrayAccessor(): void
    {
        $message = WireMessage::decode('{"op":"rpc","p":{"a":1}}');

        self::assertSame(['a' => 1], $message->array('p'));
        self::assertNull($message->array('missing'));
        self::assertNull($message->string('p'));
    }
}
