<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\HealthCheck;

use Mammatus\Http\Server\Configuration\Realm;

final class HealthCheckRealm implements Realm
{
    private const REALM_NAME = 'healthz';

    public function name(): string
    {
        return self::REALM_NAME;
    }
}
