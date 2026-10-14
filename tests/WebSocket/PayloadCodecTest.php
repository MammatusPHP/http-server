<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\UnableToHydrateObject;
use EventSauce\ObjectHydrator\UnableToSerializeObject;
use Mammatus\DevApp\Http\Server\WebSocket\PingParams;
use Mammatus\DevApp\Http\Server\WebSocket\PingResult;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend as FrontendHydrator;
use Mammatus\Http\Server\WebSocket\PayloadCodec;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class PayloadCodecTest extends TestCase
{
    private PayloadCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new PayloadCodec(new FrontendHydrator());
    }

    #[Test]
    public function roundTripDto(): void
    {
        $dto = new PingParams(message: 'hi');

        /** @var array<string, mixed> $wire */
        $wire = $this->codec->serialize($dto);
        self::assertEquals($dto, $this->codec->hydrate(PingParams::class, $wire));
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
        $serialized = $this->codec->serialize(new PingResult(pong: true, echo: 'a'));

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
        $this->codec->hydrate(PingParams::class, []);
    }
}
