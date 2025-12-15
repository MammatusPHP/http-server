<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use JsonSerializable;
use Mammatus\Http\Server\Attributes\ProbeType;
use Mammatus\Http\Server\Attributes\Route;
use Mammatus\Http\Server\Attributes\Vhost;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Handler implements ItemContract, JsonSerializable
{
    /**
     * @param class-string      $class
     * @param class-string|null $payload
     * @param array<ProbeType>  $probeTypes
     *
     * @phpstan-ignore ergebnis.noConstructorParameterWithDefaultValue,ergebnis.noParameterWithNullableTypeDeclaration,ergebnis.noParameterWithNullDefaultValue
     */
    public function __construct(
        public string $class,
        public string $method,
        public bool $static,
        public array $probeTypes,
        public Vhost $vhost,
        public Route $route,
        public string|null $payload = null,
    ) {
    }

    /** @return array{class: class-string, method: string, static: bool, probeTypes: array<ProbeType>, payload: class-string|null} */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'method' => $this->method,
            'static' => $this->static,
            'probeTypes' => $this->probeTypes,
            'vhost' => $this->vhost,
            'route' => $this->route,
            'payload' => $this->payload,
        ];
    }
}
