<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use Mammatus\Groups\Attributes\Group;
use Mammatus\Groups\Type;
use Mammatus\Http\Server\Attributes\Probe as ProbeAttribute;
use Mammatus\Http\Server\Attributes\Route as RouteAttribute;
use Mammatus\Http\Server\Attributes\Vhost as VhostAttribute;
use Mammatus\Http\Server\Attributes\WebSocket\Channel as ChannelAttribute;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc as RpcAttribute;
use Mammatus\Http\Server\Configuration\Vhost as VhostContract;
use Mammatus\Http\Server\Webroot\WebrootPath;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionAttribute;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionIntersectionType;
use Roave\BetterReflection\Reflection\ReflectionNamedType;
use Roave\BetterReflection\Reflection\ReflectionUnionType;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;
use WyriHaximus\Composer\GenerativePluginTooling\ItemCollector;

use function array_map;
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
                yield from $this->webSocket($class);

                break;
            case in_array(VhostAttribute::class, array_map(static fn (ReflectionAttribute $ra): string => $ra->getName(), $class->getAttributes()), true):
                yield from $this->webSocket($class);

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
        /** @var array<\ReflectionAttribute<Group>> $groupAttributes */
        $serviceAttributes = new \ReflectionClass($className)->getAttributes(\Mammatus\Kubernetes\Attributes\Service::class);
        /** @var array<\ReflectionAttribute<\Mammatus\Kubernetes\Attributes\Ingress>> $ingressAttributes */
        $ingressAttributes = new \ReflectionClass($className)->getAttributes(\Mammatus\Kubernetes\Attributes\Ingress::class);
        $groups            = [];
        if (count($groupAttributes) === 0) {
            $groups[] = new Group(
                Type::Normal,
                'vhost-' . $className::name(),
            );
        } else {
            foreach ($groupAttributes as $groupAttribute) {
                $groups[] = $groupAttribute->newInstance();
            }
        }

        foreach ($groups as $group) {
            yield new Server(
                $className,
                $className::name(),
                $className::port(),
                $webroot instanceof WebrootPath ? $webroot->path() : '',
                $group,
            );

            foreach ($serviceAttributes as $serviceAttribute) {
                yield new Service(
                    $className::name(),
                    $group->name,
                    $className::port(),
                );
            }

            foreach ($ingressAttributes as $ingressAttribute) {
                $ingress = $ingressAttribute->newInstance();

                yield new Ingress(
                    $className::name(),
                    $ingress->host,
                    $ingress->path,
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

                        yield new Handler(
                            $class->getName(),
                            $method->getName(),
                            $method->isStatic(),
                            $probeTypes,
                            $vhost,
                            $route,
                            /**
                             * It's a class-string coming out of this regardless
                             *
                             * @phpstan-ignore argument.type
                             */
                            $eventType->getName(),
                        );
                    }
                }
            }
        }
    }

    /** @return iterable<ItemContract> */
    private function webSocket(ReflectionClass $class): iterable
    {
        foreach (new \ReflectionClass($class->getName())->getAttributes(VhostAttribute::class) as $vhostAttribute) {
            $vhost = $vhostAttribute->newInstance();

            foreach (new \ReflectionClass($class->getName())->getAttributes(ChannelAttribute::class) as $channelAttribute) {
                $channel = $channelAttribute->newInstance();

                /** @var class-string $payloadClass */
                $payloadClass = $channel->payloadClass !== '' ? $channel->payloadClass : $class->getName();

                yield new WebSocketChannelRegistration(
                    $vhost,
                    $channel,
                    $payloadClass,
                );
            }

            foreach (new \ReflectionClass($class->getName())->getMethods() as $method) {
                if (! $method->isPublic() || $method->isConstructor() || $method->isDestructor()) {
                    continue;
                }

                foreach ($method->getAttributes(RpcAttribute::class) as $rpcAttribute) {
                    $rpc = $rpcAttribute->newInstance();

                    $parameters = $method->getParameters();

                    if (count($parameters) === 1) {
                        $type = $parameters[0]->getType();
                        if ($type instanceof \ReflectionNamedType && $type->getName() === ServerRequestInterface::class) {
                            yield new WebSocketHandler(
                                $class->getName(),
                                $method->getName(),
                                $method->isStatic(),
                                $vhost,
                                $rpc,
                                '',
                                $this->namedReturnClass($method) ?? '',
                            );

                            continue;
                        }
                    }

                    if (count($parameters) !== 2) {
                        continue;
                    }

                    $firstType  = $parameters[0]->getType();
                    $secondType = $parameters[1]->getType();
                    if (
                        ! ($firstType instanceof \ReflectionNamedType)
                        || ! ($secondType instanceof \ReflectionNamedType)
                        || $secondType->getName() !== ServerRequestInterface::class
                        || $firstType->isBuiltin()
                    ) {
                        continue;
                    }

                    yield new WebSocketHandler(
                        $class->getName(),
                        $method->getName(),
                        $method->isStatic(),
                        $vhost,
                        $rpc,
                        $firstType->getName(),
                        $this->namedReturnClass($method) ?? '',
                    );
                }
            }
        }
    }

    private function namedReturnClass(ReflectionMethod $method): string|null
    {
        $returnType = $method->getReturnType();
        if (! ($returnType instanceof \ReflectionNamedType)) {
            return null;
        }

        return $returnType->getName();
    }
}
