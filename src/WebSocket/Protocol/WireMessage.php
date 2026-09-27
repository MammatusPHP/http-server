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
    /** @param array<string, mixed> $data */
    private function __construct(
        public string $op,
        public array $data,
    ) {
    }

    /** @throws JsonException */
    public static function decode(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $op   = $data['op'] ?? '';
        if (! is_string($op) || $op === '') {
            throw new JsonException('Missing op');
        }

        unset($data['op']);

        return new self($op, $data);
    }

    /** @param array<string, mixed> $fields */
    public static function encode(string $op, array $fields): string
    {
        return json_encode(['op' => $op, ...$fields], JSON_THROW_ON_ERROR);
    }

    public function string(string $key): string|null
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed>|null */
    public function array(string $key): array|null
    {
        $value = $this->data[$key] ?? null;

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = $value;

        return $payload;
    }
}
