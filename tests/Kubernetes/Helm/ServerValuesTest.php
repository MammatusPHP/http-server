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
        $values = Values::createFromFile();
        self::assertSame([
            'deployments' => [
                'app' => [
                    'name' => 'app',
                    'command' => 'mammatus',
                    'arguments' => [0 => 'app'],
                    'addOns' => [],
                ],
            ],

        ], $values->get());

        new ServerValues()->values($values);
        self::assertSame([
            'services' => [
                'frontend' => [
                    'name' => 'frontend',
                    'group' => 'app',
                    'port' => 1337,
                ],
            ],
            'deployments' => [
                'app' => [
                    'name' => 'app',
                    'command' => 'mammatus',
                    'arguments' => [0 => 'app'],
                    'addOns' => [
                        [
                            'helper' => 'mammatus.container.port',
                            'type' => 'container',
                            'arguments' => [
                                'name' => 'frontend',
                                'containerPort' => 1337,
                            ],
                        ],
                        [
                            'helper' => 'mammatus.container.port',
                            'type' => 'container',
                            'arguments' => [
                                'name' => 'healthz',
                                'containerPort' => 9666,
                            ],
                        ],
                        [
                            'helper' => 'mammatus.container.probe',
                            'type' => 'container',
                            'arguments' => [
                                'liveness' =>  [
                                    'path' => '/probe/liveness',
                                    'vhost' => 'healthz',
                                ],
                                'readiness' =>  [
                                    'path' => '/probe/readiness',
                                    'vhost' => 'healthz',
                                ],
                                'startUp' => [
                                    'path' => '/probe/startup',
                                    'vhost' => 'healthz',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $values->get());
    }
}
