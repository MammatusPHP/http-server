<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\UnableToHydrateObject;
use EventSauce\ObjectHydrator\UnableToSerializeObject;
use Mammatus\DevApp\Http\Server\WebSocketDemoEvent;
use Mammatus\DevApp\Http\Server\WebSocketPingParams;
use Mammatus\DevApp\Http\Server\WebSocketPingResult;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use WyriHaximus\TestUtilities\TestCase;

final class FrontendObjectMapperTest extends TestCase
{
    private Frontend $mapper;

    protected function setUp(): void
    {
        $this->mapper = new Frontend();
    }

    #[Test]
    public function hydrateAndSerializeDemoEvent(): void
    {
        $object = $this->mapper->hydrateObject(WebSocketDemoEvent::class, ['message' => 'x']);

        self::assertSame(['message' => 'x'], $this->mapper->serializeObject($object));
    }

    #[Test]
    public function hydrateAndSerializePingParams(): void
    {
        $object = $this->mapper->hydrateObject(WebSocketPingParams::class, ['message' => 'p']);

        self::assertSame(['message' => 'p'], $this->mapper->serializeObject($object));
    }

    #[Test]
    public function hydrateAndSerializePingResult(): void
    {
        $object = $this->mapper->hydrateObject(WebSocketPingResult::class, ['pong' => true, 'echo' => 'e']);

        self::assertSame(['pong' => true, 'echo' => 'e'], $this->mapper->serializeObject($object));
    }

    #[Test]
    public function hydrateObjectsAndSerializeObjects(): void
    {
        $objects = $this->mapper->hydrateObjects(WebSocketPingParams::class, [
            ['message' => 'a'],
            ['message' => 'b'],
        ]);

        /** @phpstan-ignore argument.type (hydrateObjects returns object list; serializeObjects accepts the same iterable at runtime) */
        $serialized = $this->mapper->serializeObjects($objects);

        self::assertSame([['message' => 'a'], ['message' => 'b']], [...$serialized]);
    }

    #[Test]
    public function unknownClassThrows(): void
    {
        $this->expectException(UnableToHydrateObject::class);
        $this->mapper->hydrateObject(self::class, []);
    }

    #[Test]
    public function missingRequiredFieldThrows(): void
    {
        $this->expectException(UnableToHydrateObject::class);
        $this->mapper->hydrateObject(WebSocketPingParams::class, []);
    }

    #[Test]
    public function serializeUnknownObjectThrows(): void
    {
        $this->expectException(UnableToSerializeObject::class);
        $this->mapper->serializeObject(new stdClass());
    }
}
