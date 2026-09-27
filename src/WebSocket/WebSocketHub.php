<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use EventSauce\ObjectHydrator\UnableToHydrateObject;
use JsonException;
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

final class WebSocketHub
{
    /** @var array<string, list<Connection>> */
    private array $channels = [];

    /** @var array<string, callable> */
    private array $rpcHandlers = [];

    /** @var list<Connection> */
    private array $connections = [];

    private TimerInterface|null $heartbeatTimer = null;

    public function __construct(
        private readonly WebSocketPayloadCodec $codec,
        private readonly float|null $heartbeatIntervalSeconds,
    ) {
    }

    /** @param callable(mixed, ServerRequestInterface): mixed $handler */
    public function registerRpc(string $method, callable $handler, string $paramsClass, string $returnClass): void
    {
        $codec                      = $this->codec;
        $this->rpcHandlers[$method] = function (Connection $connection, WireMessage $message, ServerRequestInterface $request) use ($handler, $paramsClass, $returnClass, $codec): void {
            $id = $message->string('i');
            try {
                $paramsPayload = $message->array('p') ?? [];
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

        $frame = WireMessage::encode('evt', ['c' => $channel, 'd' => $data]);
        foreach ($this->channels[$channel] ?? [] as $connection) {
            $this->send($connection, $frame);
        }
    }

    public function onConnect(Connection $connection, ServerRequestInterface $upgradeRequest): void
    {
        $this->attach($connection, $upgradeRequest);
    }

    private function onMessage(Connection $connection, ServerRequestInterface $upgradeRequest, string $payload): void
    {
        try {
            $message = WireMessage::decode($payload);
        } catch (JsonException) {
            return;
        }

        if ($message->op === 'sub') {
            $this->subscribe($connection, $message->string('c') ?? '');

            return;
        }

        if ($message->op === 'unsub') {
            $this->unsubscribe($connection, $message->string('c') ?? '');

            return;
        }

        if ($message->op !== 'rpc') {
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

    private function dispatchRpc(Connection $connection, ServerRequestInterface $request, WireMessage $message): void
    {
        $method = $message->string('m') ?? '';
        if ($method === '' || ! array_key_exists($method, $this->rpcHandlers)) {
            $this->sendRpcError($connection, $message->string('i'), 'method_not_found', 'No RPC method ' . $method);

            return;
        }

        ($this->rpcHandlers[$method])($connection, $message, $request);
    }

    private function sendRpcResult(Connection $connection, string|null $id, mixed $result, string $returnClass): void
    {
        try {
            $serialized = $returnClass !== '' && is_object($result) ? $this->codec->serialize($result) : $this->codec->serialize($result);
        } catch (Throwable $throwable) {
            $this->sendRpcError($connection, $id, 'invalid_payload', $throwable->getMessage());

            return;
        }

        $this->send($connection, WireMessage::encode('res', ['i' => $id, 'r' => $serialized]));
    }

    private function sendRpcError(Connection $connection, string|null $id, string $code, string $msg): void
    {
        $fields = ['e' => $code, 'msg' => $msg];
        if ($id !== null) {
            $fields['i'] = $id;
        }

        $this->send($connection, WireMessage::encode('err', $fields));
    }

    private function send(Connection $connection, string|Frame $data): bool
    {
        $sent = true;
        try {
            $connection->send($data);
        } catch (Throwable) {
            $sent = false;
        }

        return $sent;
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
        if ($this->heartbeatIntervalSeconds === null || $this->heartbeatTimer instanceof TimerInterface) {
            return;
        }

        $this->heartbeatTimer = Loop::get()->addPeriodicTimer($this->heartbeatIntervalSeconds, function (): void {
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
