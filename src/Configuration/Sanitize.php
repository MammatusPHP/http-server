<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

use Mammatus\Http\Server\Webroot\WebrootPath;
use function assert;

final class Sanitize
{
    public static function sanitize(string $string): string
    {
        return preg_replace('/[^a-z_]+/', '_', $string);
    }
}
