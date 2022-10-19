<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

use function Safe\preg_replace;

final class Sanitize
{
    /**
     * @psalm-suppress InvalidReturnType
     */
    public static function sanitize(string $string): string // phpcs:disable
    {
        /**
         * @psalm-suppress InvalidReturnStatement
         */
        return preg_replace('/[^a-z_]+/', '_', strtolower($string));
    }
}
