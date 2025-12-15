<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use JsonSerializable;
use Mammatus\Groups\Attributes\Group;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class Server implements ItemContract, JsonSerializable
{
    /** @param class-string $class */
    public function __construct(
        public string $class,
        public string $name,
        public int $port,
        public string $webrootPath,
        public Group $group,
    ) {
    }

    /** @return array{class: class-string, name: string, port: int, webrootPath: string, group: array{type: 'daemon'|'normal', name: string}} */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'name' => $this->name,
            'port' => $this->port,
            'webrootPath' => $this->webrootPath,
            'group' => [
                'type' => $this->group->type->value,
                'name' => $this->group->name,
            ],
        ];
    }
}
