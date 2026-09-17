<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use JsonSerializable;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Ingress implements ItemContract, JsonSerializable
{
    public function __construct(
        public string $name,
        public string $host,
        public string $path,
    ) {
    }

    /** @return array{name: string, host: string, path: string} */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'host' => $this->host,
            'path' => $this->path,
        ];
    }
}
