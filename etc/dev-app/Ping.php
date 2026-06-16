<?php

declare(strict_types=1);

namespace Mammatus\DevApp\Http\Server;

use Mammatus\Http\Server\Handler\Input;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Ping implements Input
{
    public function __construct(public string $name)
    {
    }

    /**
     * @param array{name: string} $params
     *
     * @phpstan-ignore method.childParameterType
     */
    public static function create(ServerRequestInterface $request, array $params): self
    {
        return new self($params['name']);
    }
}
