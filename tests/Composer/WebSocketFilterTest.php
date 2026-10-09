<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Composer;

use Mammatus\DevApp\Http\Server\WebSocketPingHandler;
use Mammatus\Http\Server\Attributes;
use Mammatus\Http\Server\Composer\Plugin;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use WyriHaximus\Composer\GenerativePluginTooling\ClassFilter;
use WyriHaximus\Composer\GenerativePluginTooling\Composer\ASTLocatorStore;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\HasAttributes;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Class\IsInstantiable;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalAnd as ClassLogicalAnd;
use WyriHaximus\Composer\GenerativePluginTooling\Filter\Operators\Class\LogicalOr as ClassLogicalOr;
use WyriHaximus\TestUtilities\TestCase;

use function count;
use function dirname;

final class WebSocketFilterTest extends TestCase
{
    #[Test]
    public function webSocketRpcHandlerPassesPluginClassFilters(): void
    {
        $class = $this->reflect(WebSocketPingHandler::class);

        /** @var list<ClassFilter> $classFilters */
        $classFilters = [];
        foreach ((new Plugin())->filters() as $filter) {
            if (! ($filter instanceof ClassFilter)) {
                continue;
            }

            $classFilters[] = $filter;
        }

        self::assertTrue((new IsInstantiable())($class));
        self::assertTrue((new HasAttributes(Attributes\Vhost::class))($class));
        self::assertTrue((new ClassLogicalAnd(new IsInstantiable(), new HasAttributes(Attributes\Vhost::class)))($class));

        self::assertNotSame([], $classFilters);
        self::assertInstanceOf(ClassLogicalOr::class, $classFilters[0]);
        $logicalOrProperty = new ReflectionProperty(ClassLogicalOr::class, 'filters');
        /** @var list<ClassFilter> $orFilters */
        $orFilters = $logicalOrProperty->getValue($classFilters[0]);
        self::assertGreaterThanOrEqual(3, count($orFilters));
        $any = false;
        foreach ($orFilters as $orFilter) {
            if (! $orFilter($class)) {
                continue;
            }

            $any = true;
        }

        self::assertTrue($any);

        foreach ($classFilters as $classFilter) {
            self::assertTrue($classFilter($class), $classFilter::class . ' rejected ' . WebSocketPingHandler::class);
        }
    }

    /** @param class-string $className */
    private function reflect(string $className): ReflectionClass
    {
        $root      = dirname(__DIR__, 2);
        $reflector = new DefaultReflector(
            new AggregateSourceLocator([
                (new MakeLocatorForComposerJsonAndInstalledJson())($root, ASTLocatorStore::ASTLocator()),
            ]),
        );

        return $reflector->reflectClass($className);
    }
}
