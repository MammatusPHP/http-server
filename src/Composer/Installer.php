<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use Chimera\Mapping\Routing;
use Chimera\Routing\Handler as RoutingHandler;
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Support\Collection;
use Mammatus\Http\Server\Annotations\Vhost as VhostAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Broadcast as BroadcastAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Rpc as RpcAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Subscription as SubscriptionAnnotation;
use Mammatus\Http\Server\Configuration\Bus;
use Mammatus\Http\Server\Configuration\Handler;
use Mammatus\Http\Server\Configuration\Server;
use Mammatus\Http\Server\Configuration\Vhost;
use Mammatus\Http\Server\Configuration\VhostStub;
use Mammatus\Http\Server\Configuration\WebSocket\Broadcast;
use Mammatus\Http\Server\Configuration\WebSocket\Realm;
use Mammatus\Http\Server\Configuration\WebSocket\Rpc;
use Mammatus\Http\Server\Configuration\WebSocket\Subscription;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function defined;
use function dirname;
use function explode;
use function file_exists;
use function function_exists;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function microtime;
use function property_exists;
use function round;
use function rtrim;
use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\spl_autoload_register;
use function Safe\sprintf;
use function Safe\substr;
use function str_replace;
use function strlen;
use function strpos;
use function WyriHaximus\getIn;
use function WyriHaximus\iteratorOrArrayToArray;
use function WyriHaximus\listClassesInDirectories;
use function WyriHaximus\Twig\render;

use const DIRECTORY_SEPARATOR;

/**
 * @infection-ignore-all
 */
final class Installer implements PluginInterface, EventSubscriberInterface
{
    private const ROUTE_BEHAVIOR = [
        Routing\CreateEndpoint::class          => RoutingHandler\CreateOnly::class,
        Routing\CreateAndFetchEndpoint::class  => RoutingHandler\CreateAndFetch::class,
        Routing\ExecuteEndpoint::class         => RoutingHandler\ExecuteOnly::class,
        Routing\ExecuteAndFetchEndpoint::class => RoutingHandler\ExecuteAndFetch::class,
        Routing\FetchEndpoint::class           => RoutingHandler\FetchOnly::class,
//        Routing\SimpleEndpoint::class          => 'none',
    ];

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => 'findVhosts'];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /**
     * Called before every dump autoload, generates a fresh PHP class.
     */
    public static function findVhosts(Event $event): void
    {
        $start    = microtime(true);
        $io       = $event->getIO();
        $composer = $event->getComposer();

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        assert(is_string($vendorDir));

        // Composer is bugged and doesn't handle root package autoloading properly yet
        $packages   = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packages[] = $composer->getPackage();
        foreach ($packages as $package) {
            if (! array_key_exists('psr-4', $package->getAutoload())) {
                continue;
            }

            /**
             * @psalm-suppress PossiblyUndefinedArrayOffset
             */
            foreach ($package->getAutoload()['psr-4'] as $ns => $pa) {
                if (is_string($pa)) {
                    $pa = [$pa];
                }

                foreach ($pa as $p) {
                    if ($package instanceof RootPackageInterface) {
                        $p = dirname($vendorDir) . '/' . $p;
                    } else {
                        $p = dirname($vendorDir) . '/vendor/' . $package->getName() . '/' . $p;
                    }

                    if (substr($p, -1) !== '/') {
                        $p .= '/';
                    }

                    spl_autoload_register(static function ($class) use ($ns, $p): void {
                        if (strpos($class, $ns) !== 0) {
                            return;
                        }

                        $fileName = $p . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($ns))) . '.php';
                        if (! file_exists($fileName)) {
                            return;
                        }

                        include $fileName;
                    });
                }
            }
        }

        if (! function_exists('WyriHaximus\iteratorOrArrayToArray')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/iterator-or-array-to-array/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\listClassesInDirectories')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/list-classes-in-directory/src/functions_include.php';
        }

        if (! function_exists('WyriHaximus\getIn')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/string-get-in/src/functions_include.php';
        }

        if (! defined('WyriHaximus\Constants\Numeric\ONE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Numeric/constants_include.php';
        }

        if (! defined('WyriHaximus\Constants\Boolean\TRUE')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/constants/src/Boolean/constants_include.php';
        }

        if (! function_exists('igorw\get_in')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/igorw/get-in/src/get_in.php';
        }

        if (! function_exists('Safe\file_get_contents')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/filesystem.php';
        }

        if (! function_exists('Safe\sprintf')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/thecodingmachine/safe/generated/strings.php';
        }

        if (! function_exists('WyriHaximus\Twig\render')) {
            /** @psalm-suppress UnresolvableInclude */
            require_once $composer->getConfig()->get('vendor-dir') . '/wyrihaximus/simple-twig/src/functions_include.php';
        }

        $io->write('<info>mammatus/http-server:</info> Locating vhosts');

        $vhosts = self::findAllVhosts($composer, $io);

        $classContents = render(
            file_get_contents(
                self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage()) . '/etc/generated_templates/AbstractConfiguration.php.twig'
            ),
            ['servers' => $vhosts]
        );

        $installPath = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
            . '/src/Generated/AbstractConfiguration.php';

        file_put_contents($installPath, $classContents);
        chmod($installPath, 0664);

        foreach ($vhosts as $vhost) {
            if ($vhost->hasRpcs() || $vhost->hasSubscriptions()) {
                $classContents = render(
                    file_get_contents(
                        self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage()) . '/etc/generated_templates/RouterFactory_.php.twig'
                    ),
                    ['server' => $vhost]
                );
                $installPath   = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
                    . '/src/Generated/RouterFactory_' . $vhost->vhost->nameSanitized . '.php';
                file_put_contents($installPath, $classContents);
                chmod($installPath, 0664);
            }

            foreach ($vhost->busses as $bus) {
                $classContents = render(
                    file_get_contents(
                        self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage()) . '/etc/generated_templates/CommandHandlerMiddlewareFactory_.php.twig'
                    ),
                    ['server' => $vhost, 'bus' => $bus]
                );
                $installPath   = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
                    . '/src/Generated/CommandHandlerMiddlewareFactory_' . $vhost->vhost->nameSanitized . '_' . $bus->nameSanitized . '.php';
                file_put_contents($installPath, $classContents);
                chmod($installPath, 0664);
            }
        }

        $io->write(sprintf(
            '<info>mammatus/http-server:</info> Generated static abstract vhost(s) configuration in %s second(s)',
            round(microtime(true) - $start, 2)
        ));
    }

    /**
     * Find the location where to put the generate PHP class in.
     */
    private static function locateRootPackageInstallPath(
        Config $composerConfig,
        RootPackageInterface $rootPackage
    ): string {
        // You're on your own
        if ($rootPackage->getName() === 'mammatus/http-server') {
            $vendorDir = $composerConfig->get('vendor-dir');
            assert(is_string($vendorDir));

            return dirname($vendorDir);
        }

        return $composerConfig->get('vendor-dir') . '/mammatus/http-server';
    }

    /**
     * @return array<Server>
     */
    private static function findAllVhosts(Composer $composer, IOInterface $io): array
    {
        $annotationReader = new AnnotationReader();
        $vendorDir        = $composer->getConfig()->get('vendor-dir');
        assert(is_string($vendorDir));
        assert(file_exists($vendorDir));
        retry:
        try {
            $classReflector = new DefaultReflector(
                (new MakeLocatorForComposerJsonAndInstalledJson())(dirname($vendorDir), (new BetterReflection())->astLocator()),
            );
        } catch (InvalidPrefixMapping $invalidPrefixMapping) {
            mkdir(explode('" is not a', explode('" for prefix "', $invalidPrefixMapping->getMessage())[1])[0]);
            goto retry;
        }

        $packages   = $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $packages[] = $composer->getPackage();
        /**
         * @psalm-suppress PossiblyUndefinedVariable
         */
        $classes    = static fn (): Collection => self::classes($packages, $vendorDir, $classReflector, $io); /** @phpstan-ignore-line */
        $flatVhosts = $classes()->filter(static function (ReflectionClass $class): bool {
            return $class->implementsInterface(Vhost::class);
        })->map(static function (ReflectionClass $class): ReflectionClass {
            if (! class_exists($class->getName(), false)) {
                /**
                 * @psalm-suppress UnresolvableInclude
                 */
                require $class->getFileName();
            }

            return $class;
        })->
        map(static fn (ReflectionClass $class): string => $class->getName())->
        map(static fn (string $vhost): VhostStub => new VhostStub(
            $vhost,
            ($vhost . '::port')(), /** @phpstan-ignore-line */
            ($vhost . '::name')(), /** @phpstan-ignore-line */
            ($vhost . '::webroot')(), /** @phpstan-ignore-line */
            ($vhost . '::maxConcurrentRequests')(), /** @phpstan-ignore-line */
        ))->
        all();

        $vhosts = [];
        try {
            $io->write(sprintf('<info>mammatus/http-server:</info> Found %s vhost(s)', count($flatVhosts)));
            $vhosts = [];

            foreach ($flatVhosts as $vhost) {
                $vhosts[] = new Server(
                    $vhost,
                    (static function (array $handlers): array {
                        $realms = $busses = $rpcs = $subscriptions = $broadcasts = [];
                        foreach ($handlers as $handler) {
                            if (array_key_exists(BroadcastAnnotation::class, $handler['annotations'])) {
                                foreach ($handler['annotations'][BroadcastAnnotation::class] as $annotation) {
                                    $broadcasts[$annotation->realm()][] = new Broadcast($handler['class']);
                                }
                            }

                            if (array_key_exists(RpcAnnotation::class, $handler['annotations'])) {
                                foreach ($handler['annotations'][RpcAnnotation::class] as $annotation) {
                                    $rpcs[$annotation->realm()][] = new Rpc(
                                        $annotation->rpc(),
                                        $annotation->command(),
                                        $annotation->bus(),
                                        $annotation->transformer(),
                                    );
                                    $busses[]                     = $annotation->bus();
                                }
                            }

                            if (! array_key_exists(SubscriptionAnnotation::class, $handler['annotations'])) {
                                continue;
                            }

                            foreach ($handler['annotations'][SubscriptionAnnotation::class] as $annotation) {
                                $subscriptions[$annotation->realm()][] = new Subscription(
                                    $annotation->topic(),
                                    $annotation->command(),
                                    $annotation->bus(),
                                    $annotation->transformer(),
                                );
                                $busses[]                              = $annotation->bus();
                            }
                        }

                        foreach (array_unique(array_merge(array_keys($broadcasts), array_keys($rpcs), array_keys($subscriptions))) as $realm) {
                            $realms[] = new Realm(
                                (string) $realm,
                                $rpcs[$realm] ?? [],
                                $subscriptions[$realm] ?? [],
                                $broadcasts[$realm] ?? [],
                                array_unique($busses),
                            );
                        }

                        return $realms;
                    })(
                        $classes()->map(static function (ReflectionClass $class): ReflectionClass {
                            if (! class_exists($class->getName(), false)) {
                                /**
                                 * @psalm-suppress UnresolvableInclude
                                 */
                                require $class->getFileName();
                            }

                            return $class;
                        })->flatMap(static function (ReflectionClass $class) use ($annotationReader): array {
                            $annotations = [];
                            foreach ($annotationReader->getClassAnnotations(new \ReflectionClass($class->getName())) as $annotation) {
                                $annotations[$annotation::class][] = $annotation;
                            }

                            return [
                                [
                                    'class' => $class->getName(),
                                    'annotations' => $annotations,
                                ],
                            ];
                        })->filter(static function (array $classNAnnotations): bool {
                            if (! array_key_exists(VhostAnnotation::class, $classNAnnotations['annotations'])) {
                                return false;
                            }

                            if (array_key_exists(BroadcastAnnotation::class, $classNAnnotations['annotations'])) {
                                return true;
                            }

                            if (array_key_exists(SubscriptionAnnotation::class, $classNAnnotations['annotations'])) {
                                return true;
                            }

                            if (array_key_exists(RpcAnnotation::class, $classNAnnotations['annotations'])) {
                                return true;
                            }

                            foreach ($classNAnnotations['annotations'] as $annotations) {
                                foreach ($annotations as $annotation) {
                                    if (is_subclass_of($annotation, Routing\Endpoint::class)) {
                                        return true;
                                    }
                                }
                            }

                            return false;
                        })->filter(static function (array $classNAnnotations) use ($vhost): bool {
                            return in_array($vhost->name, array_map(static fn (VhostAnnotation $vhost): string => $vhost->vhost(), $classNAnnotations['annotations'][VhostAnnotation::class]), true);
                        })->all()
                    ),
                    (static function (array $handlers): array {
                        $serverHandlers = [];
                        foreach ($handlers as $handler) {
                            foreach ($handler['annotations'] as $annotations) {
                                foreach ($annotations as $annotation) {
                                    /**
                                     * @psalm-suppress PossiblyInvalidPropertyFetch
                                     */
                                    if (! is_subclass_of($annotation, Routing\Endpoint::class) || $annotation->path === null || $annotation->app === null) {
                                        continue;
                                    }

                                    /** @phpstan-ignore-next-line */
                                    if (property_exists($annotation, 'query')) {
                                        /**
                                         * @psalm-suppress UndefinedPropertyFetch
                                         * @psalm-suppress PossiblyInvalidPropertyFetch
                                         * @psalm-suppress InvalidArrayOffset
                                         */
                                        $serverHandlers[] = new Handler(
                                            $annotation->methods,
                                            $annotation->app,
                                            $annotation->query,
                                            $handler['class'],
                                            self::ROUTE_BEHAVIOR[$annotation::class],
                                            $annotation->path,
                                        );
                                    }

                                    /** @phpstan-ignore-next-line */
                                    if (! property_exists($annotation, 'command')) {
                                        continue;
                                    }

                                    /**
                                     * @psalm-suppress UndefinedPropertyFetch
                                     * @psalm-suppress PossiblyInvalidPropertyFetch
                                     * @psalm-suppress InvalidArrayOffset
                                     */
                                    $serverHandlers[] = new Handler(
                                        $annotation->methods,
                                        $annotation->app,
                                        $annotation->command,
                                        $handler['class'],
                                        self::ROUTE_BEHAVIOR[$annotation::class],
                                        $annotation->path,
                                    );
                                }
                            }
                        }

                        return $serverHandlers;
                    })(
                        $classes()->map(static function (ReflectionClass $class): ReflectionClass {
                            if (! class_exists($class->getName(), false)) {
                                /**
                                 * @psalm-suppress UnresolvableInclude
                                 */
                                require $class->getFileName();
                            }

                            return $class;
                        })->flatMap(static function (ReflectionClass $class) use ($annotationReader): array {
                            $annotations = [];
                            foreach ($annotationReader->getClassAnnotations(new \ReflectionClass($class->getName())) as $annotation) {
                                $annotations[$annotation::class][] = $annotation;
                            }

                            return [
                                [
                                    'class' => $class->getName(),
                                    'annotations' => $annotations,
                                ],
                            ];
                        })->filter(static function (array $classNAnnotations): bool {
                            if (! array_key_exists(VhostAnnotation::class, $classNAnnotations['annotations'])) {
                                return false;
                            }

                            foreach ($classNAnnotations['annotations'] as $annotations) {
                                foreach ($annotations as $annotation) {
                                    if (is_subclass_of($annotation, Routing\Endpoint::class)) {
                                        return true;
                                    }
                                }
                            }

                            return false;
                        })->filter(static function (array $classNAnnotations) use ($vhost): bool {
                            return in_array($vhost->name, array_map(static fn (VhostAnnotation $vhost): string => $vhost->vhost(), $classNAnnotations['annotations'][VhostAnnotation::class]), true);
                        })->all()
                    ),
                    (static function (array $handlers): array {
                        $busses = [];
                        foreach ($handlers as $handler) {
                            foreach ($handler['annotations'] as $annotations) {
                                foreach ($annotations as $annotation) {
                                    if (! is_subclass_of($annotation, Routing\Endpoint::class)) {
                                        continue;
                                    }

                                    /**
                                     * @psalm-suppress UndefinedPropertyFetch
                                     * @psalm-suppress PossiblyInvalidPropertyFetch
                                     */
                                    $busses[] = $annotation->app;
                                }
                            }

                            if (array_key_exists(RpcAnnotation::class, $handler['annotations'])) {
                                foreach ($handler['annotations'][RpcAnnotation::class] as $annotation) {
                                    $busses[] = $annotation->bus();
                                }
                            }

                            if (! array_key_exists(SubscriptionAnnotation::class, $handler['annotations'])) {
                                continue;
                            }

                            foreach ($handler['annotations'][SubscriptionAnnotation::class] as $annotation) {
                                $busses[] = $annotation->bus();
                            }
                        }

                        $busInstances = [];
                        foreach (array_unique($busses) as $bus) {
                            assert(is_string($bus));
                            $busInstances[] = new Bus(
                                $bus,
                                ...array_filter(
                                    array_map(
                                        static function (array $handler) use ($bus) {
                                            foreach ($handler['annotations'] as $annotations) {
                                                foreach ($annotations as $annotation) {
                                                    if (! is_subclass_of($annotation, Routing\Endpoint::class)) {
                                                        continue;
                                                    }

                                                    /**
                                                     * @psalm-suppress UndefinedPropertyFetch
                                                     * @psalm-suppress PossiblyInvalidPropertyFetch
                                                     */
                                                    if ($annotation->app !== $bus) {
                                                        continue;
                                                    }

                                                    if (property_exists($annotation, 'query')) {
                                                        /**
                                                         * @psalm-suppress UndefinedPropertyFetch
                                                         * @psalm-suppress PossiblyInvalidPropertyFetch
                                                         */
                                                        return new Bus\Handler(
                                                            $bus,
                                                            $annotation->query,
                                                            $handler['class'],
                                                        );
                                                    }

                                                    if (property_exists($annotation, 'command')) {
                                                        /**
                                                         * @psalm-suppress UndefinedPropertyFetch
                                                         * @psalm-suppress PossiblyInvalidPropertyFetch
                                                         */
                                                        return new Bus\Handler(
                                                            $bus,
                                                            $annotation->command,
                                                            $handler['class'],
                                                        );
                                                    }
                                                }
                                            }

                                            if (array_key_exists(RpcAnnotation::class, $handler['annotations'])) {
                                                foreach ($handler['annotations'][RpcAnnotation::class] as $annotation) {
                                                    return new Bus\Handler(
                                                        $bus,
                                                        $annotation->command(),
                                                        $handler['class'],
                                                    );
                                                }
                                            }

                                            if (! array_key_exists(SubscriptionAnnotation::class, $handler['annotations'])) {
                                                return;
                                            }

                                            foreach ($handler['annotations'][SubscriptionAnnotation::class] as $annotation) {
                                                return new Bus\Handler(
                                                    $bus,
                                                    $annotation->command(),
                                                    $handler['class'],
                                                );
                                            }
                                        },
                                        array_values($handlers),
                                    ),
                                    static fn (?Bus\Handler $handler) => $handler !== null,
                                ),
                            );
                        }

                        return $busInstances;
                    })(
                        $classes()->map(static function (ReflectionClass $class): ReflectionClass {
                            if (! class_exists($class->getName(), false)) {
                                /**
                                 * @psalm-suppress UnresolvableInclude
                                 */
                                require $class->getFileName();
                            }

                            return $class;
                        })->flatMap(static function (ReflectionClass $class) use ($annotationReader): array {
                            $annotations = [];
                            foreach ($annotationReader->getClassAnnotations(new \ReflectionClass($class->getName())) as $annotation) {
                                $annotations[$annotation::class][] = $annotation;
                            }

                            return [
                                [
                                    'class' => $class->getName(),
                                    'annotations' => $annotations,
                                ],
                            ];
                        })->filter(static function (array $classNAnnotations): bool {
                            if (! array_key_exists(VhostAnnotation::class, $classNAnnotations['annotations'])) {
                                return false;
                            }

                            if (array_key_exists(SubscriptionAnnotation::class, $classNAnnotations['annotations'])) {
                                return true;
                            }

                            if (array_key_exists(RpcAnnotation::class, $classNAnnotations['annotations'])) {
                                return true;
                            }

                            foreach ($classNAnnotations['annotations'] as $annotations) {
                                foreach ($annotations as $annotation) {
                                    if (is_subclass_of($annotation, Routing\Endpoint::class)) {
                                        return true;
                                    }
                                }
                            }

                            return false;
                        })->filter(static function (array $classNAnnotations) use ($vhost): bool {
                            return in_array($vhost->name, array_map(static fn (VhostAnnotation $vhost): string => $vhost->vhost(), $classNAnnotations['annotations'][VhostAnnotation::class]), true);
                        })->all()
                    )
                );
            }
        } catch (Throwable $throwable) { /** @phpstan-ignore-line */
            $io->write(sprintf('<info>mammatus/http-server:</info> Unexpected error: <fg=red>%s</>', (string) $throwable));
        }

        return $vhosts;
    }

    /**
     * @param array<PackageInterface> $packages
     */
    private static function classes(array $packages, string $vendorDir, DefaultReflector $classReflector, IOInterface $io): Collection // phpcs:disable
    {
        return (new Collection($packages))->filter(static function (PackageInterface $package): bool {
            return count($package->getAutoload()) > 0;
        })->filter(static function (PackageInterface $package): bool {
            return (bool) getIn($package->getExtra(), 'mammatus.http.server.has-vhosts', false);
        })->filter(static function (PackageInterface $package): bool {
            return array_key_exists('classmap', $package->getAutoload()) || array_key_exists('psr-4', $package->getAutoload());
        })->flatMap(static function (PackageInterface $package) use ($vendorDir): array {
            $packageName = $package->getName();
            $autoload    = $package->getAutoload();
            $paths       = [];
            foreach (['classmap', 'psr-4'] as $item) {
                if (! array_key_exists($item, $autoload)) {
                    continue;
                }

                foreach ($autoload[$item] as $path) {
                    if (is_string($path)) {
                        if ($package instanceof RootPackageInterface) {
                            $paths[] = dirname($vendorDir) . DIRECTORY_SEPARATOR . $path;
                        } else {
                            $paths[] = $vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $path;
                        }
                    }

                    if (! is_array($path)) {
                        continue;
                    }

                    foreach ($path as $p) {
                        if ($package instanceof RootPackageInterface) {
                            $paths[] = dirname($vendorDir) . DIRECTORY_SEPARATOR . $p;
                        } else {
                            $paths[] = $vendorDir . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . $p;
                        }
                    }
                }
            }

            return $paths;
        })->map(static function (string $path): string {
            return rtrim($path, '/');
        })->filter(static function (string $path): bool {
            return file_exists($path);
        })->flatMap(static function (string $path): array {
            return iteratorOrArrayToArray(
                listClassesInDirectories($path)
            );
        })->flatMap(static function (string $class) use ($classReflector, $io): array {
            try {
                /** @psalm-suppress PossiblyUndefinedVariable */
                return [
                    (static function (ReflectionClass $reflectionClass): ReflectionClass {
                        $reflectionClass->getInterfaces();
                        $reflectionClass->getMethods();

                        return $reflectionClass;
                    })($classReflector->reflectClass($class)),
                ];
            } catch (IdentifierNotFound $identifierNotFound) {
                $io->write(sprintf(
                    '<info>mammatus/http-server:</info> Error while reflecting "<fg=cyan>%s</>": <fg=yellow>%s</>',
                    $class,
                    $identifierNotFound->getMessage()
                ));
            }

            return [];
        })->filter(static function (ReflectionClass $class): bool {
            return $class->isInstantiable();
        });
    }
}
