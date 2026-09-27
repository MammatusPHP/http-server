<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\UnableToHydrateObject;
use EventSauce\ObjectHydrator\UnableToSerializeObject;

use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

final readonly class WebSocketPayloadCodec
{
    public function __construct(
        private ObjectMapper $mapper,
    ) {
    }

    /**
     * @param class-string         $className
     * @param array<string, mixed> $payload
     */
    public function hydrate(string $className, array $payload): object
    {
        try {
            return $this->mapper->hydrateObject($className, $payload);
        } catch (UnableToHydrateObject $throwable) {
            throw $throwable;
        }
    }

    public function serialize(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (! is_object($value)) {
            throw UnableToSerializeObject::dueToError('mixed');
        }

        return $this->mapper->serializeObject($value);
    }
}
