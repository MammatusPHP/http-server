<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\UnableToHydrateObject;
use JsonException;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Publish;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Subscribe;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Unsubscribe;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Call;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Error;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Result;
use Mammatus\Http\Server\WebSocket\Protocol\WireMessage;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\Frame;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use Throwable;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;
use function is_object;
use function microtime;

final class Hub
{
    /** @var array<string, list<Connection>> */
    private array $channels = [];

    /** @var array<string, callable> */
    private array $rpcHandlers = [];

    /** @var list<Connection> */
    private array $connections = [];

    private TimerInterface|null $heartbeatTimer = null;

    /** @var callable(Connection, string): void|null */
    private $channelSubscribeCallback = null;

    public function __construct(
        private readonly PayloadCodec $codec,
        private readonly float $heartbeatIntervalSeconds,
        private readonly string $heartbeatChannel,
    ) {
    }

    /** @param callable(mixed, ServerRequestInterface): mixed $handler */
    public function registerRpc(string $method, callable $handler, string $paramsClass, string $returnClass): void
    {
        $codec                      = $this->codec;
        $this->rpcHandlers[$method] = function (Connection $connection, Call $call, ServerRequestInterface $request) use ($handler, $paramsClass, $returnClass, $codec): void {
            $id = $call->id;
            try {
                $paramsPayload = $call->payload;
                if ($paramsClass === '') {
                    $params = $paramsPayload;
                } else {
                    /** @var class-string $paramsClassName */
                    $paramsClassName = $paramsClass;
                    $params          = $codec->hydrate($paramsClassName, $paramsPayload);
                }

                $result = $handler($params, $request);
                if ($result instanceof PromiseInterface) {
                    $result->then(
                        function (mixed $resolved) use ($connection, $id, $returnClass): void {
                            $this->sendRpcResult($connection, $id, $resolved, $returnClass);
                        },
                        function (Throwable $throwable) use ($connection, $id): void {
                            $this->sendRpcError($connection, $id, 'rpc_failed', $throwable->getMessage());
                        },
                    );

                    return;
                }

                $this->sendRpcResult($connection, $id, $result, $returnClass);
            } catch (UnableToHydrateObject | JsonException $throwable) {
                $this->sendRpcError($connection, $id, 'invalid_payload', $throwable->getMessage());
            } catch (Throwable $throwable) {
                $this->sendRpcError($connection, $id, 'rpc_failed', $throwable->getMessage());
            }
        };
    }

    public function attach(Connection $connection, ServerRequestInterface $upgradeRequest): void
    {
        $this->connections[] = $connection;
        $connection->on('message', function (string $payload) use ($connection, $upgradeRequest): void {
            $this->onMessage($connection, $upgradeRequest, $payload);
        });
        $connection->on('close', function () use ($connection): void {
            $this->detach($connection);
        });
        $connection->on('error', function () use ($connection): void {
            $this->detach($connection);
        });
        $this->ensureHeartbeat();
    }

    /** @api */
    public function publish(string $channel, mixed $payload): void
    {
        try {
            $data = $this->codec->serialize($payload);
        } catch (Throwable) {
            return;
        }

        $frame = WireMessage::encode(new Publish('', $channel, $data));
        foreach ($this->channels[$channel] ?? [] as $connection) {
            $this->send($connection, $frame);
        }
    }

    public function onConnect(Connection $connection, ServerRequestInterface $upgradeRequest): void
    {
        $this->attach($connection, $upgradeRequest);
    }

    /** @param callable(Connection, string): void $callback */
    public function onChannelSubscribe(callable $callback): void
    {
        $this->channelSubscribeCallback = $callback;
    }

    private function onMessage(Connection $connection, ServerRequestInterface $upgradeRequest, string $payload): void
    {
        try {
            $message = WireMessage::decode($payload);
        } catch (JsonException) {
            return;
        }

        if ($message instanceof Subscribe) {
            $this->subscribe($connection, $message->channel);

            return;
        }

        if ($message instanceof Unsubscribe) {
            $this->unsubscribe($connection, $message->channel);

            return;
        }

        if (! ($message instanceof Call)) {
            return;
        }

        $this->dispatchRpc($connection, $upgradeRequest, $message);
    }

    private function subscribe(Connection $connection, string $channel): void
    {
        if ($channel === '') {
            return;
        }

        if (! array_key_exists($channel, $this->channels)) {
            $this->channels[$channel] = [];
        }

        if (in_array($connection, $this->channels[$channel], true)) {
            return;
        }

        $this->channels[$channel][] = $connection;

        if ($this->channelSubscribeCallback === null) {
            return;
        }

        ($this->channelSubscribeCallback)($connection, $channel);
    }

    private function unsubscribe(Connection $connection, string $channel): void
    {
        if ($channel === '' || ! array_key_exists($channel, $this->channels)) {
            return;
        }

        $this->channels[$channel] = array_values(array_filter(
            $this->channels[$channel],
            static fn (Connection $candidate): bool => $candidate !== $connection,
        ));
    }

    private function dispatchRpc(Connection $connection, ServerRequestInterface $request, Call $call): void
    {
        if ($call->method === '' || ! array_key_exists($call->method, $this->rpcHandlers)) {
            $this->sendRpcError($connection, $call->id, 'method_not_found', 'No RPC method ' . $call->method);

            return;
        }

        ($this->rpcHandlers[$call->method])($connection, $call, $request);
    }

    private function sendRpcResult(Connection $connection, string $id, mixed $result, string $returnClass): void
    {
        try {
            $serialized = $returnClass !== '' && is_object($result) ? $this->codec->serialize($result) : $this->codec->serialize($result);
        } catch (Throwable $throwable) {
            $this->sendRpcError($connection, $id, 'invalid_payload', $throwable->getMessage());

            return;
        }

        $this->send($connection, WireMessage::encode(new Result($id, $serialized)));
    }

    private function sendRpcError(Connection $connection, string $id, string $code, string $msg): void
    {
        $this->send($connection, WireMessage::encode(new Error($id, $code, $msg)));
    }

    private function send(Connection $connection, string|Frame $data): bool
    {
        try {
            $connection->send($data);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function detach(Connection $connection): void
    {
        $this->connections = array_values(array_filter(
            $this->connections,
            static fn (Connection $candidate): bool => $candidate !== $connection,
        ));

        foreach ($this->channels as $channel => $connections) {
            $this->channels[$channel] = array_values(array_filter(
                $connections,
                static fn (Connection $candidate): bool => $candidate !== $connection,
            ));
        }

        if ($this->connections !== []) {
            return;
        }

        $this->cancelHeartbeat();
    }

    private function ensureHeartbeat(): void
    {
        if ($this->heartbeatTimer instanceof TimerInterface) {
            return;
        }

        $this->heartbeatTimer = Loop::get()->addPeriodicTimer($this->heartbeatIntervalSeconds, function (): void {
            $this->publish($this->heartbeatChannel, microtime(true));
            $ping = new Frame('', true, Frame::OP_PING);
            foreach ($this->connections as $connection) {
                if ($this->send($connection, $ping)) {
                    continue;
                }

                $this->detach($connection);
            }
        });
    }

    private function cancelHeartbeat(): void
    {
        if (! $this->heartbeatTimer instanceof TimerInterface) {
            return;
        }

        Loop::get()->cancelTimer($this->heartbeatTimer);
        $this->heartbeatTimer = null;
    }
}
