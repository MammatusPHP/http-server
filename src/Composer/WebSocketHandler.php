<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Composer;

use JsonSerializable;
use Mammatus\Http\Server\Attributes\Vhost;
use Mammatus\Http\Server\Attributes\WebSocket\Rpc;
use WyriHaximus\Composer\GenerativePluginTooling\Item as ItemContract;

final readonly class WebSocketHandler implements ItemContract, JsonSerializable
{
    /**
     * @param class-string $class
     * @param string       $paramsClass class-string, or empty when the RPC method has no params DTO
     * @param string       $returnClass class-string, builtin name, or empty for void
     */
    public function __construct(
        public string $class,
        public string $method,
        public bool $static,
        public Vhost $vhost,
        public Rpc $rpc,
        public string $paramsClass,
        public string $returnClass,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->class,
            'method' => $this->method,
            'static' => $this->static,
            'vhost' => $this->vhost,
            'rpc' => $this->rpc,
            'paramsClass' => $this->paramsClass,
            'returnClass' => $this->returnClass,
        ];
    }
}
