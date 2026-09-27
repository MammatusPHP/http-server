<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Server\WebSocket\Hydrators;

use EventSauce\ObjectHydrator\IterableList;
use EventSauce\ObjectHydrator\ObjectMapper;
use EventSauce\ObjectHydrator\UnableToHydrateObject;
use EventSauce\ObjectHydrator\UnableToSerializeObject;
use Generator;

class Frontend implements ObjectMapper
{
    private array $hydrationStack = [];
    public function __construct() {}

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function hydrateObject(string $className, array $payload): object
    {
        return match($className) {
            'Mammatus\DevApp\Http\Server\WebSocket\DemoEvent' => $this->hydrateMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️DemoEvent($payload),
                'Mammatus\DevApp\Http\Server\WebSocket\PingParams' => $this->hydrateMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingParams($payload),
                'Mammatus\DevApp\Http\Server\WebSocket\PingResult' => $this->hydrateMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingResult($payload),
            default => throw UnableToHydrateObject::noHydrationDefined($className, $this->hydrationStack),
        };
    }
    
            
    private function hydrateMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️DemoEvent(array $payload): \Mammatus\DevApp\Http\Server\WebSocket\DemoEvent
    {
        $properties = []; 
        $missingFields = [];
        try {
            $value = $payload['message'] ?? null;

            if ($value === null) {
                $missingFields[] = 'message';
                goto after_message;
            }

            $properties['message'] = $value;

            after_message:

        } catch (\Throwable $exception) {
            throw UnableToHydrateObject::dueToError('Mammatus\DevApp\Http\Server\WebSocket\DemoEvent', $exception, stack: $this->hydrationStack);
        }

        if (count($missingFields) > 0) {
            throw UnableToHydrateObject::dueToMissingFields(\Mammatus\DevApp\Http\Server\WebSocket\DemoEvent::class, $missingFields, stack: $this->hydrationStack);
        }

        try {
            return new \Mammatus\DevApp\Http\Server\WebSocket\DemoEvent(...$properties);
        } catch (\Throwable $exception) {
            throw UnableToHydrateObject::dueToError('Mammatus\DevApp\Http\Server\WebSocket\DemoEvent', $exception, stack: $this->hydrationStack);
        }
    }

        
    private function hydrateMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingParams(array $payload): \Mammatus\DevApp\Http\Server\WebSocket\PingParams
    {
        $properties = []; 
        $missingFields = [];
        try {
            $value = $payload['message'] ?? null;

            if ($value === null) {
                $missingFields[] = 'message';
                goto after_message;
            }

            $properties['message'] = $value;

            after_message:

        } catch (\Throwable $exception) {
            throw UnableToHydrateObject::dueToError('Mammatus\DevApp\Http\Server\WebSocket\PingParams', $exception, stack: $this->hydrationStack);
        }

        if (count($missingFields) > 0) {
            throw UnableToHydrateObject::dueToMissingFields(\Mammatus\DevApp\Http\Server\WebSocket\PingParams::class, $missingFields, stack: $this->hydrationStack);
        }

        try {
            return new \Mammatus\DevApp\Http\Server\WebSocket\PingParams(...$properties);
        } catch (\Throwable $exception) {
            throw UnableToHydrateObject::dueToError('Mammatus\DevApp\Http\Server\WebSocket\PingParams', $exception, stack: $this->hydrationStack);
        }
    }

        
    private function hydrateMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingResult(array $payload): \Mammatus\DevApp\Http\Server\WebSocket\PingResult
    {
        $properties = []; 
        $missingFields = [];
        try {
            $value = $payload['pong'] ?? null;

            if ($value === null) {
                $missingFields[] = 'pong';
                goto after_pong;
            }

            $properties['pong'] = $value;

            after_pong:

            $value = $payload['echo'] ?? null;

            if ($value === null) {
                $missingFields[] = 'echo';
                goto after_echo;
            }

            $properties['echo'] = $value;

            after_echo:

        } catch (\Throwable $exception) {
            throw UnableToHydrateObject::dueToError('Mammatus\DevApp\Http\Server\WebSocket\PingResult', $exception, stack: $this->hydrationStack);
        }

        if (count($missingFields) > 0) {
            throw UnableToHydrateObject::dueToMissingFields(\Mammatus\DevApp\Http\Server\WebSocket\PingResult::class, $missingFields, stack: $this->hydrationStack);
        }

        try {
            return new \Mammatus\DevApp\Http\Server\WebSocket\PingResult(...$properties);
        } catch (\Throwable $exception) {
            throw UnableToHydrateObject::dueToError('Mammatus\DevApp\Http\Server\WebSocket\PingResult', $exception, stack: $this->hydrationStack);
        }
    }
    
    private function serializeViaTypeMap(string $accessor, object $object, array $payloadToTypeMap): array
    {
        foreach ($payloadToTypeMap as $payloadType => [$valueType, $method]) {
            if (is_a($object, $valueType)) {
                return [$accessor => $payloadType] + $this->{$method}($object);
            }
        }

        throw new \LogicException('No type mapped for object of class: ' . get_class($object));
    }

    public function serializeObject(object $object): mixed
    {
        return $this->serializeObjectOfType($object, get_class($object));
    }

    /**
     * @template T
     *
     * @param T               $object
     * @param class-string<T> $className
     */
    public function serializeObjectOfType(object $object, string $className): mixed
    {
        try {
            return match($className) {
                'array' => $this->serializeValuearray($object),
            'Ramsey\Uuid\UuidInterface' => $this->serializeValueRamsey⚡️Uuid⚡️UuidInterface($object),
            'DateTime' => $this->serializeValueDateTime($object),
            'DateTimeImmutable' => $this->serializeValueDateTimeImmutable($object),
            'DateTimeInterface' => $this->serializeValueDateTimeInterface($object),
            'Mammatus\DevApp\Http\Server\WebSocket\DemoEvent' => $this->serializeObjectMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️DemoEvent($object),
            'Mammatus\DevApp\Http\Server\WebSocket\PingParams' => $this->serializeObjectMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingParams($object),
            'Mammatus\DevApp\Http\Server\WebSocket\PingResult' => $this->serializeObjectMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingResult($object),
                default => throw new \LogicException("No serialization defined for $className"),
            };
        } catch (\Throwable $exception) {
            throw UnableToSerializeObject::dueToError($className, $exception);
        }
    }
    
    
    private function serializeValuearray(mixed $value): mixed
    {
        static $serializer;
        
        if ($serializer === null) {
            $serializer = new \EventSauce\ObjectHydrator\PropertySerializers\SerializeArrayItems(...array (
));
        }
        
        return $serializer->serialize($value, $this);
    }


    private function serializeValueRamsey⚡️Uuid⚡️UuidInterface(mixed $value): mixed
    {
        static $serializer;
        
        if ($serializer === null) {
            $serializer = new \EventSauce\ObjectHydrator\PropertySerializers\SerializeUuidToString(...array (
));
        }
        
        return $serializer->serialize($value, $this);
    }


    private function serializeValueDateTime(mixed $value): mixed
    {
        static $serializer;
        
        if ($serializer === null) {
            $serializer = new \EventSauce\ObjectHydrator\PropertySerializers\SerializeDateTime(...array (
));
        }
        
        return $serializer->serialize($value, $this);
    }


    private function serializeValueDateTimeImmutable(mixed $value): mixed
    {
        static $serializer;
        
        if ($serializer === null) {
            $serializer = new \EventSauce\ObjectHydrator\PropertySerializers\SerializeDateTime(...array (
));
        }
        
        return $serializer->serialize($value, $this);
    }


    private function serializeValueDateTimeInterface(mixed $value): mixed
    {
        static $serializer;
        
        if ($serializer === null) {
            $serializer = new \EventSauce\ObjectHydrator\PropertySerializers\SerializeDateTime(...array (
));
        }
        
        return $serializer->serialize($value, $this);
    }


    private function serializeObjectMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️DemoEvent(mixed $object): mixed
    {
        \assert($object instanceof \Mammatus\DevApp\Http\Server\WebSocket\DemoEvent);
        $result = [];

        $message = $object->message;
        after_message:        $result['message'] = $message;


        return $result;
    }


    private function serializeObjectMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingParams(mixed $object): mixed
    {
        \assert($object instanceof \Mammatus\DevApp\Http\Server\WebSocket\PingParams);
        $result = [];

        $message = $object->message;
        after_message:        $result['message'] = $message;


        return $result;
    }


    private function serializeObjectMammatus⚡️DevApp⚡️Http⚡️Server⚡️WebSocket⚡️PingResult(mixed $object): mixed
    {
        \assert($object instanceof \Mammatus\DevApp\Http\Server\WebSocket\PingResult);
        $result = [];

        $pong = $object->pong;
        after_pong:        $result['pong'] = $pong;

        
        $echo = $object->echo;
        after_echo:        $result['echo'] = $echo;


        return $result;
    }
    
    

    /**
     * @template T
     *
     * @param class-string<T> $className
     * @param iterable<array> $payloads;
     *
     * @return IterableList<T>
     *
     * @throws UnableToHydrateObject
     */
    public function hydrateObjects(string $className, iterable $payloads): IterableList
    {
        return new IterableList($this->doHydrateObjects($className, $payloads));
    }

    private function doHydrateObjects(string $className, iterable $payloads): Generator
    {
        foreach ($payloads as $index => $payload) {
            yield $index => $this->hydrateObject($className, $payload);
        }
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     * @param iterable<array> $payloads;
     *
     * @return IterableList<T>
     *
     * @throws UnableToSerializeObject
     */
    public function serializeObjects(iterable $payloads): IterableList
    {
        return new IterableList($this->doSerializeObjects($payloads));
    }

    private function doSerializeObjects(iterable $objects): Generator
    {
        foreach ($objects as $index => $object) {
            yield $index => $this->serializeObject($object);
        }
    }
}