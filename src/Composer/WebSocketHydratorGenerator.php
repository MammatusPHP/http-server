<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use EventSauce\ObjectHydrator\ObjectMapperCodeGenerator;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Remove;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function sort;

final class WebSocketHydratorGenerator
{
    /** @param list<class-string> $classes */
    public static function generate(string $rootPath, string $vhostServerClassName, array $classes): void
    {
        $directory = $rootPath . '/src/Server/WebSocket/Hydrators';
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $targetClass = 'Mammatus\\Http\\Server\\Server\\WebSocket\\Hydrators\\' . $vhostServerClassName;
        $targetFile  = $directory . '/' . $vhostServerClassName . '.php';

        if ($classes === []) {
            Remove::fileOnlyIfItExists($targetFile);

            return;
        }

        sort($classes);

        $code = (new ObjectMapperCodeGenerator())->dump($classes, $targetClass);
        file_put_contents($targetFile, $code);
    }
}
