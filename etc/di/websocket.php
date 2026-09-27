<?php

declare(strict_types=1);

use Mammatus\Http\Server\WebSocket\Middleware\WebSocketClientAssetMiddleware;

return [
    WebSocketClientAssetMiddleware::class => static function (): WebSocketClientAssetMiddleware {
        $contents = file_get_contents(__DIR__ . '/../js/mammatus-websocket-client.mjs');
        if ($contents === false) {
            throw new RuntimeException('WebSocket client asset not found at etc/js/mammatus-websocket-client.mjs');
        }

        return new WebSocketClientAssetMiddleware($contents);
    },
];
