<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use JsonSerializable;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Service implements ItemContract, JsonSerializable
{
    public function __construct(
        public string $name,
        public string $group,
        public int $port,
    ) {
    }

    /** @return array{name: string, group: string, port: int} */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'group' => $this->group,
            'port' => $this->port,
        ];
    }
}
