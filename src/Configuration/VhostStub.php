<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration;

final class VhostStub
{
    public readonly string $classSanitized;
    public readonly string $nameSanitized;

    public function __construct(
        public readonly string $class,
        public readonly int $port,
        public readonly string $name,
        public readonly Webroot $webroot,
        public readonly ?int $maxConcurrentRequests,
    ) {
        $this->classSanitized = Sanitize::sanitize($this->class);
        $this->nameSanitized  = Sanitize::sanitize($this->name);
    }
}
