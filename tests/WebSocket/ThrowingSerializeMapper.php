<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\IterableList;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\UnableToSerializeObject;
use LogicException;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend;

final class ThrowingSerializeMapper implements ObjectMapper
{
    private readonly Frontend $inner;

    public function __construct()
    {
        $this->inner = new Frontend();
    }

    /** @param array<mixed> $payload */
    public function hydrateObject(string $className, array $payload): object
    {
        return $this->inner->hydrateObject($className, $payload);
    }

    /** @param iterable<array<mixed>> $payloads */
    public function hydrateObjects(string $className, iterable $payloads): IterableList
    {
        return $this->inner->hydrateObjects($className, $payloads);
    }

    public function serializeObject(object $object): mixed
    {
        throw UnableToSerializeObject::dueToError($object::class, new LogicException('serialize failed'));
    }

    public function serializeObjectOfType(object $object, string $className): mixed
    {
        throw UnableToSerializeObject::dueToError($className, new LogicException('serialize failed'));
    }

    /** @param iterable<object> $payloads */
    public function serializeObjects(iterable $payloads): IterableList
    {
        return $this->inner->serializeObjects($payloads);
    }
}
