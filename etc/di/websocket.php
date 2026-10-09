<?php

declare(strict_types=1);

use Mammatus\Http\Server\WebSocket\Middleware\WebSocketClientAssetMiddleware;
use Mammatus\Http\Server\WebSocket\WebSocketDefaults;

return [
    WebSocketClientAssetMiddleware::class => static function (): WebSocketClientAssetMiddleware {
        $contents = file_get_contents(__DIR__ . '/../js/mammatus-websocket-client.mjs');
        if ($contents === false) {
            throw new RuntimeException('WebSocket client asset not found at etc/js/mammatus-websocket-client.mjs');
        }

        $contents = str_replace(
            '%%MAMMATUS_WEBSOCKET_HEARTBEAT_CHANNEL%%',
            WebSocketDefaults::HEARTBEAT_CHANNEL,
            $contents,
        );

        return new WebSocketClientAssetMiddleware($contents);
    },
];
