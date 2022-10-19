<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Configuration\WebSocket;

use Mammatus\Http\Server\Configuration\Sanitize;

final class Realm
{
    public readonly string $nameSanitized;

    /**
     * @param array<Rpc>          $rpcs
     * @param array<Subscription> $subscriptions
     * @param array<Broadcast>    $broadcasts
     * @param array<string>       $busses
     */
    public function __construct(
        public readonly string $name,
        /** @var array<Rpc>  */
        public readonly array $rpcs,
        /** @var array<Subscription> */
        public readonly array $subscriptions,
        /** @var array<Broadcast> */
        public readonly array $broadcasts,
        /** @var array<string>  */
        public readonly array $busses,
    ) {
        $this->nameSanitized = Sanitize::sanitize($this->name);
    }
}
