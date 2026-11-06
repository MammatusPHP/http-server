<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol;

use JsonException;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class WireMessage
{
    /** @throws JsonException */
    public static function decode(string $json): Rpc\Call|Rpc\Result|Rpc\Error|PubSub\Subscribe|PubSub\Unsubscribe|PubSub\Publish
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return match (self::operation($data)) {
            Operation::CALL->value => new Rpc\Call(
                self::stringField($data, WireProperties::ID->value),
                self::stringField($data, WireProperties::METHOD->value),
                self::payloadField($data),
            ),
            Operation::RESULT->value => new Rpc\Result(
                self::stringField($data, WireProperties::ID->value),
                $data[WireProperties::RESULT->value] ?? null,
            ),
            Operation::ERROR->value => new Rpc\Error(
                self::stringField($data, WireProperties::ID->value),
                self::stringField($data, WireProperties::ERROR->value),
                self::stringField($data, WireProperties::MESSAGE->value),
            ),
            Operation::SUBSCRIBE->value => new PubSub\Subscribe(
                self::optionalStringField($data, WireProperties::ID->value),
                self::channelField($data),
            ),
            Operation::UNSUBSCRIBE->value => new PubSub\Unsubscribe(
                self::optionalStringField($data, WireProperties::ID->value),
                self::channelField($data),
            ),
            Operation::PUBLISH->value => new PubSub\Publish(
                self::optionalStringField($data, WireProperties::ID->value),
                self::channelField($data),
                $data[WireProperties::DATA->value] ?? null,
            ),
            default => throw new JsonException('Unknown op'),
        };
    }

    public static function encode(Rpc\Call|Rpc\Result|Rpc\Error|PubSub\Subscribe|PubSub\Unsubscribe|PubSub\Publish $message): string
    {
        return json_encode($message, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $data */
    private static function operation(array $data): string
    {
        $operation = $data[WireProperties::OPERATION->value] ?? $data['op'] ?? null;
        if (! is_string($operation) || $operation === '') {
            throw new JsonException('Missing op');
        }

        return $operation;
    }

    /** @param array<string, mixed> $data */
    private static function channelField(array $data): string
    {
        $channel = $data[WireProperties::CHANNEL->value] ?? $data['t'] ?? null;

        return is_string($channel) ? $channel : '';
    }

    /** @param array<string, mixed> $data */
    private static function stringField(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $data */
    private static function optionalStringField(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function payloadField(array $data): array
    {
        $payload = $data[WireProperties::PAYLOAD->value] ?? null;
        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $payload;

        return $normalized;
    }
}
