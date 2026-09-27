<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\UnableToHydrateObject;
use EventSauce\ObjectHydrator\UnableToSerializeObject;
use Mammatus\DevApp\Http\Server\WebSocketPingParams;
use Mammatus\DevApp\Http\Server\WebSocketPingResult;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend as FrontendHydrator;
use Mammatus\Http\Server\WebSocket\WebSocketPayloadCodec;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class WebSocketPayloadCodecTest extends TestCase
{
    private WebSocketPayloadCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new WebSocketPayloadCodec(new FrontendHydrator());
    }

    #[Test]
    public function roundTripDto(): void
    {
        $dto = new WebSocketPingParams(message: 'hi');

        /** @var array<string, mixed> $wire */
        $wire = $this->codec->serialize($dto);
        self::assertEquals($dto, $this->codec->hydrate(WebSocketPingParams::class, $wire));
    }

    #[Test]
    public function serializeScalars(): void
    {
        self::assertSame('x', $this->codec->serialize('x'));
        self::assertSame(1, $this->codec->serialize(1));
    }

    #[Test]
    public function serializeObjectUsesMapper(): void
    {
        $serialized = $this->codec->serialize(new WebSocketPingResult(pong: true, echo: 'a'));

        self::assertSame(['pong' => true, 'echo' => 'a'], $serialized);
    }

    #[Test]
    public function serializeRejectsNonObjectNonScalar(): void
    {
        $this->expectException(UnableToSerializeObject::class);
        $this->codec->serialize(['nope']);
    }

    #[Test]
    public function hydrateRethrowsUnableToHydrateObject(): void
    {
        $this->expectException(UnableToHydrateObject::class);
        $this->codec->hydrate(WebSocketPingParams::class, []);
    }
}
