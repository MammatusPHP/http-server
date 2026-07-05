<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use Mammatus\Http\Server\Attributes;
use Mammatus\Http\Server\Configuration\Vhost;
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
use function ksort;
use function sort;
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
        /** @var array<string, array{vhost: Server, server_class_name: string, handlers: array<Handler>, probes: array<Attributes\Probe>}> $vhosts */
        $vhosts = [];
        foreach ($items as $item) {
            if ($item instanceof Service) {
                $services[] = $item;
                continue;
            }

            if ($item instanceof Server) {
                if (! array_key_exists($item->name, $vhosts)) {
                    $vhosts[$item->name] = [
                        'handlers' => [],
                        'probes' => [],
                    ];
                }

                $vhosts[$item->name]['vhost']             = $item;
                $vhosts[$item->name]['server_class_name'] = ChangeCase::pascal($item->name);
            }

            if (! ($item instanceof Handler)) {
                continue;
            }

            if (! array_key_exists($item->vhost->vhost, $vhosts)) {
                $vhosts[$item->vhost->vhost] = [
                    'handlers' => [],
                    'probes' => [],
                ];
            }

            $vhosts[$item->vhost->vhost]['handlers'][] = $item;

            foreach ($item->probeTypes as $probeType) {
                $vhosts[$item->vhost->vhost]['probes'][$this->probeTypeToHelmChartPropertyName($probeType)] = $item;
            }
        }

        Remove::directoryContentsOnlyIfItExists($rootPath . '/src/Server');
        Remove::fileOnlyIfItExists($rootPath . '/src/Kubernetes/Helm/ServerValues.php');

        ksort($vhosts);
        foreach ($vhosts as $vhost) {
            ksort($vhost['probes']);
            sort($vhost['handlers']);
        }

        foreach ($vhosts as $vhost) {
            if (! array_key_exists('server_class_name', $vhost)) {
                continue;
            }

            $handlerClasses = [];
            foreach ($vhost['handlers'] as $handler) {
                if ($handler->static) {
                    continue;
                }

                $handlerClasses[$handler->class] = 'handler' . str_replace('\\', '', $handler->class);
            }

            ksort($handlerClasses);

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
            ['vhosts' => $vhosts, 'services' => $services],
        );

        TwigFile::render(
            $rootPath . '/etc/generated_templates/ShipMonkDeadCode.php.twig',
            $rootPath . '/src/PHPSan/ShipMonkDeadCode.php',
            ['vhosts' => $vhosts],
        );
    }

    private function probeTypeToHelmChartPropertyName(Attributes\ProbeType $probeType): string
    {
        return match ($probeType) {
            Attributes\ProbeType::StartUp => 'startUp',
            Attributes\ProbeType::Liveness => 'liveness',
            Attributes\ProbeType::Readiness => 'readiness',
        };
    }
}
