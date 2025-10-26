<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use Mammatus\Groups\Attributes\Group;
use Mammatus\Groups\Type;
use Mammatus\Http\Server\Attributes\Probe as ProbeAttribute;
use Mammatus\Http\Server\Attributes\Route as RouteAttribute;
use Mammatus\Http\Server\Attributes\Vhost as VhostAttribute;
use Mammatus\Http\Server\Configuration\Vhost as VhostContract;
use Mammatus\Http\Server\Webroot\WebrootPath;
use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionNamedType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;

use function array_map;
use function class_exists;
use function count;
use function in_array;

final class Collector implements ItemCollector
{
    private const array THE_NUMBER_OF_PARAMETERS_REQUIRED_FOR_A_METHOD_TO_BE_AN_EVENT_HANDLER_IS_ONE_OR_NONE = [0, 1];

    /** @return iterable<ItemContract> */
    public function collect(ReflectionClass $class): iterable
    {
        /** @phpstan-ignore ergebnis.noSwitch */
        switch (true) {
            case $class->implementsInterface(VhostContract::class):
                yield from $this->server($class);

                break;
            case in_array(VhostAttribute::class, array_map(static fn (ReflectionAttribute $ra): string => $ra->getName(), $class->getAttributes()), true) && in_array(RouteAttribute::class, array_map(static fn (ReflectionAttribute $ra): string => $ra->getName(), $class->getAttributes()), true):
                yield from $this->handler($class);

                break;
        }
    }

    /** @return iterable<ItemContract> */
    private function server(ReflectionClass $class): iterable
    {
        /** @var class-string<VhostContract> $className */
        $className = $class->getName();
        $webroot   = $className::webroot();
        /** @var array<\ReflectionAttribute<Group>> $groupAttributes */
        $groupAttributes = new \ReflectionClass($className)->getAttributes(Group::class);
        if (count($groupAttributes) === 0) {
            yield new Server(
                $className,
                $className::name(),
                $className::port(),
                $webroot instanceof WebrootPath ? $webroot->path() : '',
                new Group(
                    Type::Normal,
                    'vhost-' . $className::name(),
                ),
            );
        } else {
            foreach ($groupAttributes as $groupAttribute) {
                yield new Server(
                    $className,
                    $className::name(),
                    $className::port(),
                    $webroot instanceof WebrootPath ? $webroot->path() : '',
                    $groupAttribute->newInstance(),
                );
            }
        }
    }

    /** @return iterable<ItemContract> */
    private function handler(ReflectionClass $class): iterable
    {
        $probeTypes = [];
        foreach (new \ReflectionClass($class->getName())->getAttributes(ProbeAttribute::class) as $isProbeAttribute) {
            $probeTypes[] = $isProbeAttribute->newInstance()->type;
        }

        foreach (new \ReflectionClass($class->getName())->getAttributes(VhostAttribute::class) as $vhostAttribute) {
            $vhost = $vhostAttribute->newInstance();

            foreach (new \ReflectionClass($class->getName())->getAttributes(RouteAttribute::class) as $routeAttribute) {
                $route = $routeAttribute->newInstance();

                foreach ($class->getMethods() as $method) {
                    if (! $method->isPublic()) {
                        continue;
                    }

                    if ($method->isConstructor()) {
                        continue;
                    }

                    if ($method->isDestructor()) {
                        continue;
                    }

                    if (! in_array($method->getNumberOfParameters(), self::THE_NUMBER_OF_PARAMETERS_REQUIRED_FOR_A_METHOD_TO_BE_AN_EVENT_HANDLER_IS_ONE_OR_NONE, true)) {
                        continue;
                    }

                    if ($method->getNumberOfParameters() === 0) {
                        yield new Handler(
                            $class->getName(),
                            $method->getName(),
                            $method->isStatic(),
                            $probeTypes,
                            $vhost,
                            $route,
                        );

                        continue;
                    }

                    $eventTypeHolder = $method->getParameters()[0]->getType();
                    if ($eventTypeHolder instanceof ReflectionIntersectionType) {
                        continue;
                    }

                    if ($eventTypeHolder instanceof ReflectionUnionType) {
                        $eventTypes = $eventTypeHolder->getTypes();
                    } else {
                        $eventTypes = [$eventTypeHolder];
                    }

                    foreach ($eventTypes as $eventType) {
                        if (! ($eventType instanceof ReflectionNamedType)) {
                            continue;
                        }

                        if (! class_exists($eventType->getName(), false)) {
                            continue;
                        }

                        yield new Handler(
                            $class->getName(),
                            $method->getName(),
                            $method->isStatic(),
                            $probeTypes,
                            $vhost,
                            $route,
                            $eventType->getName(),
                        );
                    }
                }
            }
        }
    }
}
