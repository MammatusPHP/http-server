<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use JsonException;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Publish;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Subscribe;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Unsubscribe;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Call;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Result;
use Mammatus\Http\Server\WebSocket\Protocol\WireMessage;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

use function json_decode;

final class WireMessageTest extends TestCase
{
    #[Test]
    public function encodeAndDecodeCall(): void
    {
        $json    = WireMessage::encode(new Call('1', 'ping', []));
        $message = WireMessage::decode($json);

        self::assertInstanceOf(Call::class, $message);
        self::assertSame('1', $message->id);
        self::assertSame('ping', $message->method);
        self::assertSame([], $message->payload);
    }

    #[Test]
    public function decodeRejectsMissingOp(): void
    {
        $this->expectException(JsonException::class);
        WireMessage::decode('{"x":1}');
    }

    #[Test]
    public function decodeRejectsUnknownOp(): void
    {
        $this->expectException(JsonException::class);
        WireMessage::decode('{"o":"nope"}');
    }

    #[Test]
    public function decodeCallPayload(): void
    {
        $message = WireMessage::decode('{"o":"rpc","p":{"a":1}}');

        self::assertInstanceOf(Call::class, $message);
        self::assertSame(['a' => 1], $message->payload);
        self::assertSame('', $message->method);
    }

    #[Test]
    public function decodeLegacyOpKeyAndTopicAlias(): void
    {
        $message = WireMessage::decode('{"op":"sub","t":"demo"}');

        self::assertInstanceOf(Subscribe::class, $message);
        self::assertSame('demo', $message->channel);
    }

    #[Test]
    public function roundTripResultAndPublish(): void
    {
        $result = WireMessage::decode(WireMessage::encode(new Result('9', ['pong' => true])));
        self::assertInstanceOf(Result::class, $result);
        self::assertSame(['pong' => true], $result->data);

        $publish = WireMessage::decode(WireMessage::encode(new Publish('', 'events', ['x' => 1])));
        self::assertInstanceOf(Publish::class, $publish);
        self::assertSame('events', $publish->channel);
        self::assertSame(['x' => 1], $publish->data);
    }

    #[Test]
    public function encodeIncludesOptionalIdWhenPresent(): void
    {
        /** @var array<string, mixed> $sub */
        $sub = json_decode(WireMessage::encode(new Subscribe('sub-1', 'demo')), true);
        self::assertSame('sub-1', $sub['i']);

        /** @var array<string, mixed> $unsub */
        $unsub = json_decode(WireMessage::encode(new Unsubscribe('unsub-1', 'demo')), true);
        self::assertSame('unsub-1', $unsub['i']);

        /** @var array<string, mixed> $evt */
        $evt = json_decode(WireMessage::encode(new Publish('evt-1', 'demo', 1)), true);
        self::assertSame('evt-1', $evt['i']);
    }
}
