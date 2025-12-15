<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server;

use Mammatus\Groups\Contracts\LifeCycleHandler;

final class MockVhost implements LifeCycleHandler
{
    public private(set) bool $started = false;
    public private(set) bool $stopped = false;

    public static function group(): string
    {
        return 'tests';
    }

    public function start(): void
    {
        $this->started = true;
    }

    public function stop(): void
    {
        $this->stopped = true;
    }
}
