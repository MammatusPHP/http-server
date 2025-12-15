<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\Kubernetes\Helm;

use Mammatus\Http\Server\Kubernetes\Helm\ServerValues;
use Mammatus\Kubernetes\Events\Helm\Values;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\TestUtilities\TestCase;

final class ServerValuesTest extends TestCase
{
    #[Test]
    public function values(): void
    {
        $values = new Values(new Values\Groups(), new Values\Registry(), Values\ValuesFile::createFromFile());
        self::assertSame([], $values->get());

        new ServerValues()->values($values);
        self::assertSame([], $values->get());
    }
}
