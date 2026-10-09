<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use GuzzleHttp\Psr7\ServerRequest;
use Mammatus\DevApp\Http\Server\WebSocketDemoEvent;
use Mammatus\DevApp\Http\Server\WebSocketPingParams;
use Mammatus\DevApp\Http\Server\WebSocketPingResult;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend as FrontendHydrator;
use Mammatus\Http\Server\WebSocket\Connection;
use Mammatus\Http\Server\WebSocket\Protocol\WireMessage;
use Mammatus\Http\Server\WebSocket\WebSocketDefaults;
use Mammatus\Http\Server\WebSocket\WebSocketHub;
use Mammatus\Http\Server\WebSocket\WebSocketPayloadCodec;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use ReflectionClass;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function React\Promise\reject;
use function React\Promise\resolve;

final class WebSocketHubTest extends TestCase
{
    #[Test]
    public function publishDeliversEvtToSubscriber(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $connection]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'hello'));

        self::assertCount(1, $connection->sent());
        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('evt', $decoded->op);
        self::assertSame('demo-events', $decoded->string('c'));
        self::assertSame(['message' => 'hello'], $decoded->array('d'));
    }

    #[Test]
    public function rpcReturnsResult(): void
    {
        $hub = $this->hub();
        $hub->registerRpc(
            'ping',
            static function (mixed $params, ServerRequestInterface $request): WebSocketPingResult {
                self::assertInstanceOf(WebSocketPingParams::class, $params);
                self::assertSame('/', $request->getUri()->getPath());

                return new WebSocketPingResult(pong: true, echo: $params->message);
            },
            WebSocketPingParams::class,
            WebSocketPingResult::class,
        );

        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '9', 'm' => 'ping', 'p' => ['message' => 'x']]), $connection]);

        self::assertCount(1, $connection->sent());
        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('res', $decoded->op);
        self::assertSame('9', $decoded->string('i'));
        self::assertSame(['pong' => true, 'echo' => 'x'], $decoded->array('r'));
    }

    #[Test]
    public function rpcUnknownMethodReturnsErr(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '1', 'm' => 'nope', 'p' => []]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('err', $decoded->op);
        self::assertSame('method_not_found', $decoded->string('e'));
    }

    #[Test]
    public function unsubStopsEvents(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $connection]);
        $connection->emit('message', [WireMessage::encode('unsub', ['c' => 'demo-events']), $connection]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'nope'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function invalidJsonIsIgnored(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', ['not-json', $connection]);

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function closeDetachesClient(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('close', [$connection]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'x'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function errorDetachesClient(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('error', [$connection]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'x'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function channelSubscribeCallbackRunsWhenClientSubscribes(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();
        $calls      = [];

        $hub->onChannelSubscribe(static function (Connection $client, string $channel) use (&$calls): void {
            $calls[] = [$client, $channel];
        });

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $connection]);

        self::assertSame([[$connection, 'demo-events']], $calls);
    }

    #[Test]
    public function duplicateSubscribeIsIgnored(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $connection]);
        $connection->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $connection]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'once'));

        self::assertCount(1, $connection->sent());
    }

    #[Test]
    public function subscribeWithEmptyChannelIsIgnored(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', []), $connection]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'x'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function publishIgnoresUnserializablePayload(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $connection]);
        $hub->publish('demo-events', ['not', 'a', 'dto']);

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function rpcWithRawParamsArray(): void
    {
        $hub = $this->hub();
        $hub->registerRpc(
            'raw',
            static fn (mixed $params, ServerRequestInterface $request): string => is_array($params) && is_string($params['k'] ?? null)
                ? $params['k']
                : $request->getMethod(),
            '',
            '',
        );

        $connection = $this->connection();
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '2', 'm' => 'raw', 'p' => ['k' => 'v']]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('v', $decoded->string('r'));
    }

    #[Test]
    public function rpcInvalidPayloadReturnsErr(): void
    {
        $hub = $this->hub();
        $hub->registerRpc('ping', static fn (): never => throw new RuntimeException('nope'), WebSocketPingParams::class, WebSocketPingResult::class);

        $connection = $this->connection();
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '3', 'm' => 'ping', 'p' => []]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('err', $decoded->op);
        self::assertSame('invalid_payload', $decoded->string('e'));
    }

    #[Test]
    public function rpcHandlerFailureReturnsErr(): void
    {
        $hub = $this->hub();
        $hub->registerRpc(
            'boom',
            static function (mixed $params, ServerRequestInterface $request): never {
                throw new RuntimeException($request->getMethod() . (is_object($params) ? '' : ''));
            },
            WebSocketPingParams::class,
            WebSocketPingResult::class,
        );

        $connection = $this->connection();
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '4', 'm' => 'boom', 'p' => ['message' => 'x']]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('rpc_failed', $decoded->string('e'));
    }

    #[Test]
    public function rpcAsyncSuccessReturnsRes(): void
    {
        $hub = $this->hub();
        $hub->registerRpc(
            'async',
            static fn (mixed $params, ServerRequestInterface $request): PromiseInterface => resolve($request->getMethod() === 'GET' ? 'ok' : 'ok'),
            '',
            '',
        );

        $connection = $this->connection();
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '5', 'm' => 'async', 'p' => []]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('res', $decoded->op);
        /** @var array<string, mixed> $raw */
        $raw = json_decode($connection->sent()[0], true);
        self::assertSame('ok', $raw['r']);
    }

    #[Test]
    public function rpcAsyncFailureReturnsErr(): void
    {
        $hub = $this->hub();
        $hub->registerRpc(
            'asyncFail',
            static fn (mixed $params, ServerRequestInterface $request): PromiseInterface => reject(new RuntimeException($request->getUri()->getPath())),
            '',
            '',
        );

        $connection = $this->connection();
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '6', 'm' => 'asyncFail', 'p' => []]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('rpc_failed', $decoded->string('e'));
    }

    #[Test]
    public function rpcInvalidReturnSerializationReturnsErr(): void
    {
        $hub = new WebSocketHub(new WebSocketPayloadCodec(new ThrowingSerializeMapper()), null, WebSocketDefaults::HEARTBEAT_CHANNEL);
        $hub->registerRpc(
            'badReturn',
            static fn (mixed $params, ServerRequestInterface $request): WebSocketPingResult => new WebSocketPingResult(
                pong: true,
                echo: $request->getUri()->getPath() !== '' ? 'x' : 'x',
            ),
            WebSocketPingParams::class,
            WebSocketPingResult::class,
        );

        $connection = $this->connection();
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '7', 'm' => 'badReturn', 'p' => ['message' => 'x']]), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('invalid_payload', $decoded->string('e'));
    }

    #[Test]
    public function detachRemovesConnectionFromChannelOnly(): void
    {
        $hub    = $this->hub();
        $first  = $this->connection();
        $second = $this->connection();

        $hub->onConnect($first, new ServerRequest('GET', '/'));
        $hub->onConnect($second, new ServerRequest('GET', '/'));
        $first->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $first]);
        $second->emit('message', [WireMessage::encode('sub', ['c' => 'demo-events']), $second]);
        $first->emit('close', [$first]);
        $hub->publish('demo-events', new WebSocketDemoEvent(message: 'only-second'));

        self::assertSame([], $first->sent());
        self::assertCount(1, $second->sent());
    }

    #[Test]
    public function heartbeatPingAndCancelWhenLastClientLeaves(): void
    {
        $hub        = new WebSocketHub(new WebSocketPayloadCodec(new FrontendHydrator()), 13.0, WebSocketDefaults::HEARTBEAT_CHANNEL);
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $this->invokeHeartbeat($hub);
        $connection->emit('close', [$connection]);
        $this->invokeHeartbeatTimerCancelled($hub);
    }

    #[Test]
    public function heartbeatPublishesEvtToHeartbeatChannelSubscribers(): void
    {
        $hub        = new WebSocketHub(new WebSocketPayloadCodec(new FrontendHydrator()), 13.0, WebSocketDefaults::HEARTBEAT_CHANNEL);
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => WebSocketDefaults::HEARTBEAT_CHANNEL]), $connection]);
        $this->invokeHeartbeat($hub);

        self::assertCount(1, $connection->sent());
        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame('evt', $decoded->op);
        self::assertSame(WebSocketDefaults::HEARTBEAT_CHANNEL, $decoded->string('c'));
        /** @var array<string, mixed> $raw */
        $raw = json_decode($connection->sent()[0], true);
        self::assertIsFloat($raw['d']);
    }

    #[Test]
    public function heartbeatDetachesWhenSendFails(): void
    {
        $hub     = new WebSocketHub(new WebSocketPayloadCodec(new FrontendHydrator()), 13.0, WebSocketDefaults::HEARTBEAT_CHANNEL);
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $connection = new FailingSendConnection(new ThroughStream(), $deflate);

        $hub->registerRpc('ok', static fn (mixed $params, ServerRequestInterface $request): string => $request->getUri()->getPath() !== '' ? 'x' : 'x', '', '');
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('rpc', ['i' => '8', 'm' => 'ok', 'p' => []]), $connection]);
        $this->invokeHeartbeat($hub);
    }

    #[Test]
    public function heartbeatPublishesOnConfiguredChannelName(): void
    {
        $channel    = 'custom-heartbeat';
        $hub        = new WebSocketHub(new WebSocketPayloadCodec(new FrontendHydrator()), 13.0, $channel);
        $connection = $this->connection();

        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $connection->emit('message', [WireMessage::encode('sub', ['c' => $channel]), $connection]);
        $this->invokeHeartbeat($hub);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertSame($channel, $decoded->string('c'));
    }

    private function hub(): WebSocketHub
    {
        return new WebSocketHub(new WebSocketPayloadCodec(new FrontendHydrator()), null, WebSocketDefaults::HEARTBEAT_CHANNEL);
    }

    private function invokeHeartbeat(WebSocketHub $hub): void
    {
        $timer = (new ReflectionClass($hub))->getProperty('heartbeatTimer');
        $timer->setAccessible(true);
        $instance = $timer->getValue($hub);
        self::assertInstanceOf(TimerInterface::class, $instance);
        ($instance->getCallback())();
    }

    private function invokeHeartbeatTimerCancelled(WebSocketHub $hub): void
    {
        $timer = (new ReflectionClass($hub))->getProperty('heartbeatTimer');
        $timer->setAccessible(true);
        self::assertNull($timer->getValue($hub));
    }

    #[Test]
    public function sendReturnsFalseWhenConnectionThrows(): void
    {
        $hub     = $this->hub();
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $send = (new ReflectionClass(WebSocketHub::class))->getMethod('send');
        $send->setAccessible(true);

        self::assertFalse($send->invoke($hub, new FailingSendConnection(new ThroughStream(), $deflate), 'payload'));
    }

    private function connection(): RecordingConnection
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);

        return new RecordingConnection(new ThroughStream(), $deflate);
    }
}
