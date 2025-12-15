<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Kubernetes\Helm;

use Mammatus\Groups\Attributes\Group;

final readonly class Vhost
{
    /** @param list<array{helper: string, type: string, arguments: array<string, mixed>}> $addOns */
    public function __construct(
        public Group $group,
        public array $addOns,
    ) {
    }
}
