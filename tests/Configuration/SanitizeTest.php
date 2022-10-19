<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Configuration;

use Mammatus\Http\Server\Configuration\Sanitize;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

final class SanitizeTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function sanitize(): void
    {
        self::assertSame('_a_z_', Sanitize::sanitize('1a-Z9'));
    }
}
