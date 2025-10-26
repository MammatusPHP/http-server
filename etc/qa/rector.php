<?php

declare(strict_types=1);

use WyriHaximus\TestUtilities\RectorConfig;

return RectorConfig::configure(dirname(__DIR__, 2))->withPaths([
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dev-app',
]);
