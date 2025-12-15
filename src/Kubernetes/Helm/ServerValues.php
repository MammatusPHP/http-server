<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Kubernetes\Helm;

use Mammatus\Http\Server\Generated\AbstractHelm;
use Mammatus\Kubernetes\Events\Helm\Values;
use WyriHaximus\Broadcast\Contracts\Listener;

final class ServerValues extends AbstractHelm implements Listener
{
    public function values(Values $values): void
    {
        foreach ($this->vhosts() as $vhost) {
            $values->addToGroup($vhost->group, $vhost->addOns);
        }
    }
}
