<?php

declare(strict_types=1);

use Mammatus\Http\Server\WebSocket\Defaults;
use Mammatus\Http\Server\WebSocket\Middleware\ClientAssetMiddleware;
use Mammatus\Http\Server\WebSocket\Protocol\Operation;
use Mammatus\Http\Server\WebSocket\Protocol\WireProperties;

return [
    ClientAssetMiddleware::class => static function (): ClientAssetMiddleware {
        $contents = file_get_contents(__DIR__ . '/../js/mammatus-websocket-client.mjs');
        if ($contents === false) {
            throw new RuntimeException('WebSocket client asset not found at etc/js/mammatus-websocket-client.mjs');
        }

        $replacements = [
            '%%MAMMATUS_WEBSOCKET_HEARTBEAT_CHANNEL%%' => Defaults::HEARTBEAT_CHANNEL,
        ];

        foreach (Operation::cases() as $case) {
            $replacements['%%MAMMATUS_WEBSOCKET_OPERATION_' . $case->name . '%%'] = $case->value;
        }

        foreach (WireProperties::cases() as $case) {
            $replacements['%%MAMMATUS_WEBSOCKET_WIRE_PROPERTY_' . $case->name . '%%'] = $case->value;
        }

        $contents = str_replace(array_keys($replacements), array_values($replacements), $contents);

        return new ClientAssetMiddleware($contents);
    },
];
