<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Mammatus\DevApp\Http\Server\WebSocket\PingParams;
use Mammatus\Http\Server\Composer\WebSocket\HydratorGenerator;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class WebSocketHydratorGeneratorTest extends TestCase
{
    #[Test]
    public function generateAndRemoveHydrator(): void
    {
        $root = $this->getTmpDir();
        $file = $root . 'src/Server/WebSocket/Hydrators/GeneratorTest.php';

        HydratorGenerator::generate($root, 'GeneratorTest', [PingParams::class]);
        self::assertFileExists($file);

        HydratorGenerator::generate($root, 'GeneratorTest', []);
        self::assertFileDoesNotExist($file);
    }
}
