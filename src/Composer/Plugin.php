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
        $vhosts = [];
        foreach ($items as $item) {
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

        Remove::directoryContents($rootPath . '/src/Generated');

        foreach ($vhosts as $vhostName => $vhost) {
            if (! array_key_exists('server_class_name', $vhost)) {
                continue;
            }

            TwigFile::render(
                $rootPath . '/etc/generated_templates/Server.php.twig',
                $rootPath . '/src/Generated/Server/' . $vhost['server_class_name'] . '.php',
                ['vhost' => $vhost],
            );
        }

        TwigFile::render(
            $rootPath . '/etc/generated_templates/AbstractHelm.php.twig',
            $rootPath . '/src/Generated/AbstractHelm.php',
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
