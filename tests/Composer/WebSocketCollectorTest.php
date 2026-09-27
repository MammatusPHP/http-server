<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Mammatus\DevApp\Http\Server\WebSocketDemoEvent;
use Mammatus\DevApp\Http\Server\WebSocketPingHandler;
use Mammatus\DevApp\Http\Server\WebSocketPingResult;
use Mammatus\Http\Server\Composer\Collector;
use Mammatus\Http\Server\Composer\WebSocketChannelRegistration;
use Mammatus\Http\Server\Composer\WebSocketHandler;
use Mammatus\Tests\Http\Server\Composer\Fixtures\WebSocketRpcRequestOnly;
use Mammatus\Tests\Http\Server\Composer\Fixtures\WebSocketRpcUnionReturn;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use WyriHaximus\Composer\GenerativePluginTooling\Composer\ASTLocatorStore;
use WyriHaximus\TestUtilities\TestCase;

use function dirname;

final class WebSocketCollectorTest extends TestCase
{
    #[Test]
    public function collectWebSocketRpcHandler(): void
    {
        $class = $this->reflect(WebSocketPingHandler::class);

        $items = [...(new Collector())->collect($class)];

        self::assertCount(1, $items);
        self::assertInstanceOf(WebSocketHandler::class, $items[0]);
        self::assertSame('ping', $items[0]->rpc->method);
    }

    #[Test]
    public function collectWebSocketChannelRegistration(): void
    {
        $class = $this->reflect(WebSocketDemoEvent::class);

        $items = [...(new Collector())->collect($class)];

        self::assertCount(1, $items);
        self::assertInstanceOf(WebSocketChannelRegistration::class, $items[0]);
        self::assertSame('demo-events', $items[0]->channel->channel);
        self::assertSame(WebSocketDemoEvent::class, $items[0]->payloadClass);
    }

    #[Test]
    public function collectWebSocketRpcRequestOnlyHandler(): void
    {
        $class = $this->reflect(WebSocketRpcRequestOnly::class);

        $items = [...(new Collector())->collect($class)];

        self::assertCount(1, $items);
        self::assertInstanceOf(WebSocketHandler::class, $items[0]);
        self::assertSame('', $items[0]->paramsClass);
    }

    #[Test]
    public function collectWebSocketRpcUnionReturnType(): void
    {
        $class = $this->reflect(WebSocketRpcUnionReturn::class);

        $items = [...(new Collector())->collect($class)];

        self::assertCount(1, $items);
        self::assertInstanceOf(WebSocketHandler::class, $items[0]);
        self::assertSame('', $items[0]->returnClass);
    }

    #[Test]
    public function namedReturnClassHandlesNamedAndMissingReturnTypes(): void
    {
        $collector = new Collector();
        $method    = new ReflectionMethod(Collector::class, 'namedReturnClass');
        $method->setAccessible(true);

        self::assertSame(
            WebSocketPingResult::class,
            $method->invoke($collector, new ReflectionMethod(WebSocketPingHandler::class, 'ping')),
        );
        self::assertNull(
            $method->invoke($collector, new ReflectionMethod(WebSocketRpcUnionReturn::class, 'union')),
        );
    }

    /** @param class-string $className */
    private function reflect(string $className): ReflectionClass
    {
        $root       = dirname(__DIR__, 2);
        $astLocator = ASTLocatorStore::ASTLocator();
        $reflector  = new DefaultReflector(
            new AggregateSourceLocator([
                (new MakeLocatorForComposerJsonAndInstalledJson())($root, $astLocator),
                new DirectoriesSourceLocator([$root . '/tests/Composer/Fixtures'], $astLocator),
            ]),
        );

        return $reflector->reflectClass($className);
    }
}
