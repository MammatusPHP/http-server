<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Mammatus\DevApp\Http\Server\WebSocketPingParams;
use Mammatus\Http\Server\Composer\WebSocketHydratorGenerator;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class WebSocketHydratorGeneratorTest extends TestCase
{
    #[Test]
    public function generateAndRemoveHydrator(): void
    {
        $root = $this->getTmpDir();
        $file = $root . 'src/Server/WebSocket/Hydrators/GeneratorTest.php';

        WebSocketHydratorGenerator::generate($root, 'GeneratorTest', [WebSocketPingParams::class]);
        self::assertFileExists($file);

        WebSocketHydratorGenerator::generate($root, 'GeneratorTest', []);
        self::assertFileDoesNotExist($file);
    }
}
