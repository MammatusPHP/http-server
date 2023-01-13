<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Web;

use Mammatus\Http\Server\Web\StreamFactory;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;

final class StreamFactoryTest extends AsyncTestCase
{
    /**
     * @test
     */
    public function contents(): void
    {
        $content = 'blaat';
        $stream  = (new StreamFactory())->createStream($content);

        self::assertSame($content, (string) $stream);
    }
}
