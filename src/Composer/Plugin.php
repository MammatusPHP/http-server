<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use Mammatus\Http\Server\Attributes;
use Mammatus\Http\Server\Configuration\Vhost;
use Mammatus\Http\Server\Configuration\WebSocketVhost;
use Mammatus\Http\Server\WebSocket\WebSocketDefaults;
use Realodix\ChangeCase\ChangeCase;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\HasAttributes;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\ImplementsInterface;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\LogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\LogicalOr;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Package\ComposerJsonHasItemWithSpecificValue;
use WyriHaximus\Composer\GenerativePluginTooling\GenerativePlugin;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\Remove;
use WyriHaximus\Composer\GenerativePluginTooling\Helper\TwigFile;
use WyriHaximus\Composer\GenerativePluginTooling\Item;
use WyriHaximus\Composer\GenerativePluginTooling\LogStages;

use function array_key_exists;
use function array_values;
use function in_array;
use function is_a;
use function ksort;
use function str_replace;

final class Plugin implements GenerativePlugin
{
    public static function name(): string
    {
        return 'mammatus/http-server';
    }

    public static function log(LogStages $stage): string
    {
        return match ($stage) {
            LogStages::Init => 'Locating Virtual Hosts',
            LogStages::Error => 'An error occurred: %s',
            LogStages::Collected => 'Found %d Virtual Host(s)',
            LogStages::Completion => 'Generated Virtual Host(s) config in %s second(s)',
        };
    }

    /** @inheritDoc */
    public function filters(): iterable
    {
        yield new ComposerJsonHasItemWithSpecificValue('mammatus.http.server.has-vhosts', true);
        yield from LogicalOr::create(
            new ImplementsInterface(Vhost::class),
            ...LogicalAnd::create(
                new IsInstantiable(),
                ...LogicalAnd::create(
                    new HasAttributes(Attributes\Vhost::class),
                    new HasAttributes(Attributes\Route::class),
                ),
            ),
            ...LogicalAnd::create(
                new IsInstantiable(),
                new HasAttributes(Attributes\Vhost::class),
            ),
        );
    }

    /** @inheritDoc */
    public function collectors(): iterable
    {
        yield new Collector();
    }

    public function compile(string $rootPath, Item ...$items): void
    {
        /** @var array<Service> $services */
        $services = [];
        /** @var array<Ingress> $ingresses */
        $ingresses = [];
        /** @var array<string, array{vhost?: Server, server_class_name?: string, handlers: array<Handler>, probes: array<Attributes\Probe>, ws: array{handlers: array<string, WebSocketHandler>, channels: array<string, WebSocketChannelRegistration>}}> $vhosts */
        $vhosts = [];
        foreach ($items as $item) {
            if ($item instanceof Service) {
                $services[] = $item;
                continue;
            }

            if ($item instanceof Ingress) {
                $ingresses[] = $item;
                continue;
            }

            if ($item instanceof Server) {
                if (! array_key_exists($item->name, $vhosts)) {
                    $vhosts[$item->name] = self::emptyVhostBucket();
                }

                $vhosts[$item->name]['vhost']             = $item;
                $vhosts[$item->name]['server_class_name'] = ChangeCase::pascal($item->name);
            }

            if (! ($item instanceof Handler)) {
                continue;
            }

            if (! array_key_exists($item->vhost->vhost, $vhosts)) {
                $vhosts[$item->vhost->vhost] = self::emptyVhostBucket();
            }

            $vhosts[$item->vhost->vhost]['handlers'][$item->route->httpMethod->value . ' ' . $item->route->path] = $item;

            foreach ($item->probeTypes as $probeType) {
                $vhosts[$item->vhost->vhost]['probes'][$this->probeTypeToHelmChartPropertyName($probeType)] = $item;
            }
        }

        foreach ($items as $item) {
            if ($item instanceof WebSocketHandler) {
                if (! array_key_exists($item->vhost->vhost, $vhosts)) {
                    $vhosts[$item->vhost->vhost] = self::emptyVhostBucket();
                }

                $vhosts[$item->vhost->vhost]['ws']['handlers'][$item->rpc->method] = $item;
            }

            if (! ($item instanceof WebSocketChannelRegistration)) {
                continue;
            }

            if (! array_key_exists($item->vhost->vhost, $vhosts)) {
                $vhosts[$item->vhost->vhost] = self::emptyVhostBucket();
            }

            $vhosts[$item->vhost->vhost]['ws']['channels'][$item->channel->channel] = $item;
        }

        Remove::directoryContentsOnlyIfItExists($rootPath . '/src/Server');
        Remove::fileOnlyIfItExists($rootPath . '/src/Kubernetes/Helm/ServerValues.php');

        ksort($vhosts);
        foreach ($vhosts as $vhostName => $vhost) {
            ksort($vhosts[$vhostName]['probes']);
            ksort($vhosts[$vhostName]['handlers']);
            ksort($vhosts[$vhostName]['ws']['handlers']);
            ksort($vhosts[$vhostName]['ws']['channels']);
        }

        foreach ($vhosts as $vhost) {
            if (! array_key_exists('server_class_name', $vhost) || ! array_key_exists('vhost', $vhost)) {
                continue;
            }

            $handlerClasses = [];
            foreach ($vhost['handlers'] as $handler) {
                if ($handler->static) {
                    continue;
                }

                $handlerClasses[$handler->class] = 'handler' . str_replace('\\', '', $handler->class);
            }

            foreach ($vhost['ws']['handlers'] as $wsHandler) {
                if ($wsHandler->static) {
                    continue;
                }

                $handlerClasses[$wsHandler->class] = 'handler' . str_replace('\\', '', $wsHandler->class);
            }

            ksort($handlerClasses);

            $vhostClass = $vhost['vhost']->class;
            /** @var class-string $vhostClass */
            $implementsWebSocketVhost           = is_a($vhostClass, WebSocketVhost::class, true);
            $vhost['webSocketServeClientAsset'] = $implementsWebSocketVhost && $vhostClass::webSocketServeClientAsset();
            $vhost['hasWebSocketHub']           = $vhost['ws']['handlers'] !== [] || $vhost['ws']['channels'] !== [];
            if ($vhost['hasWebSocketHub']) {
                $vhost['webSocketHeartbeatIntervalSeconds'] = $implementsWebSocketVhost
                    ? $vhostClass::webSocketHeartbeatIntervalSeconds()
                    : WebSocketDefaults::HEARTBEAT_INTERVAL_SECONDS;
            }

            WebSocketHydratorGenerator::generate(
                $rootPath,
                $vhost['server_class_name'],
                self::webSocketMapperClasses($vhost['ws']['handlers'], $vhost['ws']['channels']),
            );

            TwigFile::render(
                $rootPath . '/etc/generated_templates/Server.php.twig',
                $rootPath . '/src/Server/' . $vhost['server_class_name'] . '.php',
                [
                    'vhost' => $vhost,
                    'handlerClasses' => $handlerClasses,
                ],
            );
        }

        TwigFile::render(
            $rootPath . '/etc/generated_templates/ServerValues.php.twig',
            $rootPath . '/src/Kubernetes/Helm/ServerValues.php',
            ['vhosts' => $vhosts, 'services' => $services, 'ingresses' => $ingresses],
        );

        TwigFile::render(
            $rootPath . '/etc/generated_templates/ShipMonkDeadCode.php.twig',
            $rootPath . '/src/PHPSan/ShipMonkDeadCode.php',
            ['vhosts' => $vhosts],
        );
    }

    /** @return array{handlers: array<Handler>, probes: array<Attributes\Probe>, ws: array{handlers: array<string, WebSocketHandler>, channels: array<string, WebSocketChannelRegistration>}} */
    private static function emptyVhostBucket(): array
    {
        return [
            'handlers' => [],
            'probes' => [],
            'ws' => [
                'handlers' => [],
                'channels' => [],
            ],
        ];
    }

    private function probeTypeToHelmChartPropertyName(Attributes\ProbeType $probeType): string
    {
        return match ($probeType) {
            Attributes\ProbeType::StartUp => 'startUp',
            Attributes\ProbeType::Liveness => 'liveness',
            Attributes\ProbeType::Readiness => 'readiness',
        };
    }

    /**
     * @param array<string, WebSocketHandler>             $wsHandlers
     * @param array<string, WebSocketChannelRegistration> $wsChannels
     *
     * @return list<class-string>
     */
    private static function webSocketMapperClasses(array $wsHandlers, array $wsChannels): array
    {
        $classes = [];
        foreach ($wsHandlers as $wsHandler) {
            if ($wsHandler->paramsClass !== '') {
                $classes[$wsHandler->paramsClass] = $wsHandler->paramsClass;
            }

            if ($wsHandler->returnClass === '' || self::isBuiltinType($wsHandler->returnClass)) {
                continue;
            }

            $classes[$wsHandler->returnClass] = $wsHandler->returnClass;
        }

        foreach ($wsChannels as $wsChannel) {
            $classes[$wsChannel->payloadClass] = $wsChannel->payloadClass;
        }

        /** @var list<class-string> $classList */
        $classList = array_values($classes);

        return $classList;
    }

    private static function isBuiltinType(string $type): bool
    {
        return in_array($type, ['void', 'null', 'bool', 'int', 'float', 'string', 'array', 'mixed'], true);
    }
}
