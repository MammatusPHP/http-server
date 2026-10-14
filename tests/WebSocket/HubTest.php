<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use GuzzleHttp\Psr7\ServerRequest;
use Mammatus\DevApp\Http\Server\WebSocket\DemoEvent;
use Mammatus\DevApp\Http\Server\WebSocket\PingParams;
use Mammatus\DevApp\Http\Server\WebSocket\PingResult;
use Mammatus\Http\Server\Server\WebSocket\Hydrators\Frontend as FrontendHydrator;
use Mammatus\Http\Server\WebSocket\Connection;
use Mammatus\Http\Server\WebSocket\Defaults;
use Mammatus\Http\Server\WebSocket\Hub;
use Mammatus\Http\Server\WebSocket\PayloadCodec;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Publish;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Subscribe;
use Mammatus\Http\Server\WebSocket\Protocol\PubSub\Unsubscribe;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Call;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Error;
use Mammatus\Http\Server\WebSocket\Protocol\Rpc\Result;
use Mammatus\Http\Server\WebSocket\Protocol\WireMessage;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\ThroughStream;
use ReflectionClass;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function React\Promise\reject;
use function React\Promise\resolve;

final class HubTest extends TestCase
{
    /** @var list<Connection> */
    private array $connectionsToClose = [];

    /** @var list<Hub> */
    private array $hubsToShutdown = [];

    /** @var list<DuplexStreamInterface> */
    private array $streamsToClose = [];

    protected function tearDown(): void
    {
        foreach ($this->connectionsToClose as $connection) {
            $connection->emit('close', [$connection]);
        }

        $cancelHeartbeat = (new ReflectionClass(Hub::class))->getMethod('cancelHeartbeat');
        $cancelHeartbeat->setAccessible(true);
        foreach ($this->hubsToShutdown as $hub) {
            $cancelHeartbeat->invoke($hub);
        }

        foreach ($this->connectionsToClose as $connection) {
            $this->shutdownConnectionStream($connection);
        }

        foreach ($this->streamsToClose as $stream) {
            $stream->close();
        }

        $this->connectionsToClose = [];
        $this->hubsToShutdown     = [];
        $this->streamsToClose     = [];
    }

    #[Test]
    public function publishDeliversEvtToSubscriber(): void
    {
        $hub        = $this->hub();
        $connection = $this->attach($hub, $this->connection());
        $connection->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $connection]);
        $hub->publish('demo-events', new DemoEvent(message: 'hello'));

        self::assertCount(1, $connection->sent());
        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Publish::class, $decoded);
        self::assertSame('demo-events', $decoded->channel);
        self::assertSame(['message' => 'hello'], $decoded->data);
    }

    #[Test]
    public function rpcReturnsResult(): void
    {
        $hub = $this->hub();
        $hub->registerRpc(
            'ping',
            static function (mixed $params, ServerRequestInterface $request): PingResult {
                self::assertInstanceOf(PingParams::class, $params);
                self::assertSame('/', $request->getUri()->getPath());

                return new PingResult(pong: true, echo: $params->message);
            },
            PingParams::class,
            PingResult::class,
        );

        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('9', 'ping', ['message' => 'x'])), $connection]);

        self::assertCount(1, $connection->sent());
        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Result::class, $decoded);
        self::assertSame('9', $decoded->id);
        self::assertSame(['pong' => true, 'echo' => 'x'], $decoded->data);
    }

    #[Test]
    public function rpcUnknownMethodReturnsErr(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('1', 'nope', [])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Error::class, $decoded);
        self::assertSame('method_not_found', $decoded->code);
    }

    #[Test]
    public function unsubStopsEvents(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $connection]);
        $connection->emit('message', [WireMessage::encode(new Unsubscribe('', 'demo-events')), $connection]);
        $hub->publish('demo-events', new DemoEvent(message: 'nope'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function invalidJsonIsIgnored(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', ['not-json', $connection]);

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function closeDetachesClient(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('close', [$connection]);
        $hub->publish('demo-events', new DemoEvent(message: 'x'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function errorDetachesClient(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('error', [$connection]);
        $hub->publish('demo-events', new DemoEvent(message: 'x'));

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

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $connection]);

        self::assertSame([[$connection, 'demo-events']], $calls);
    }

    #[Test]
    public function duplicateSubscribeIsIgnored(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $connection]);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $connection]);
        $hub->publish('demo-events', new DemoEvent(message: 'once'));

        self::assertCount(1, $connection->sent());
    }

    #[Test]
    public function subscribeWithEmptyChannelIsIgnored(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', '')), $connection]);
        $hub->publish('demo-events', new DemoEvent(message: 'x'));

        self::assertSame([], $connection->sent());
    }

    #[Test]
    public function publishIgnoresUnserializablePayload(): void
    {
        $hub        = $this->hub();
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $connection]);
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
        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('2', 'raw', ['k' => 'v'])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Result::class, $decoded);
        self::assertSame('v', $decoded->data);
    }

    #[Test]
    public function rpcInvalidPayloadReturnsErr(): void
    {
        $hub = $this->hub();
        $hub->registerRpc('ping', static fn (): never => throw new RuntimeException('nope'), PingParams::class, PingResult::class);

        $connection = $this->connection();
        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('3', 'ping', [])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Error::class, $decoded);
        self::assertSame('invalid_payload', $decoded->code);
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
            PingParams::class,
            PingResult::class,
        );

        $connection = $this->connection();
        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('4', 'boom', ['message' => 'x'])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Error::class, $decoded);
        self::assertSame('rpc_failed', $decoded->code);
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
        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('5', 'async', [])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Result::class, $decoded);
        self::assertSame('ok', $decoded->data);
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
        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('6', 'asyncFail', [])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Error::class, $decoded);
        self::assertSame('rpc_failed', $decoded->code);
    }

    #[Test]
    public function rpcInvalidReturnSerializationReturnsErr(): void
    {
        $hub = new Hub(new PayloadCodec(new ThrowingSerializeMapper()), Defaults::HEARTBEAT_INTERVAL_SECONDS, Defaults::HEARTBEAT_CHANNEL);
        $hub->registerRpc(
            'badReturn',
            static fn (mixed $params, ServerRequestInterface $request): PingResult => new PingResult(
                pong: true,
                echo: $request->getUri()->getPath() !== '' ? 'x' : 'x',
            ),
            PingParams::class,
            PingResult::class,
        );

        $connection = $this->connection();
        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('7', 'badReturn', ['message' => 'x'])), $connection]);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Error::class, $decoded);
        self::assertSame('invalid_payload', $decoded->code);
    }

    #[Test]
    public function detachRemovesConnectionFromChannelOnly(): void
    {
        $hub    = $this->hub();
        $first  = $this->connection();
        $second = $this->connection();

        $first  = $this->attach($hub, $first);
        $second = $this->attach($hub, $second);
        $first->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $first]);
        $second->emit('message', [WireMessage::encode(new Subscribe('', 'demo-events')), $second]);
        $first->emit('close', [$first]);
        $hub->publish('demo-events', new DemoEvent(message: 'only-second'));

        self::assertSame([], $first->sent());
        self::assertCount(1, $second->sent());
    }

    #[Test]
    public function heartbeatPingAndCancelWhenLastClientLeaves(): void
    {
        $hub        = new Hub(new PayloadCodec(new FrontendHydrator()), 13.0, Defaults::HEARTBEAT_CHANNEL);
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $this->invokeHeartbeat($hub);
        $connection->emit('close', [$connection]);
        $this->invokeHeartbeatTimerCancelled($hub);
    }

    #[Test]
    public function heartbeatPublishesEvtToHeartbeatChannelSubscribers(): void
    {
        $hub        = new Hub(new PayloadCodec(new FrontendHydrator()), 13.0, Defaults::HEARTBEAT_CHANNEL);
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', Defaults::HEARTBEAT_CHANNEL)), $connection]);
        $this->invokeHeartbeat($hub);

        self::assertCount(1, $connection->sent());
        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Publish::class, $decoded);
        self::assertSame(Defaults::HEARTBEAT_CHANNEL, $decoded->channel);
        self::assertIsFloat($decoded->data);
    }

    #[Test]
    public function heartbeatDetachesWhenSendFails(): void
    {
        $hub     = new Hub(new PayloadCodec(new FrontendHydrator()), 13.0, Defaults::HEARTBEAT_CHANNEL);
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $connection = new FailingSendConnection($this->watchStream(new ThroughStream()), $deflate);

        $hub->registerRpc('ok', static fn (mixed $params, ServerRequestInterface $request): string => $request->getUri()->getPath() !== '' ? 'x' : 'x', '', '');
        $connection = $this->attachConnection($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Call('8', 'ok', [])), $connection]);
        $this->invokeHeartbeat($hub);
    }

    #[Test]
    public function heartbeatPublishesOnConfiguredChannelName(): void
    {
        $channel    = 'custom-heartbeat';
        $hub        = new Hub(new PayloadCodec(new FrontendHydrator()), 13.0, $channel);
        $connection = $this->connection();

        $connection = $this->attach($hub, $connection);
        $connection->emit('message', [WireMessage::encode(new Subscribe('', $channel)), $connection]);
        $this->invokeHeartbeat($hub);

        $decoded = WireMessage::decode($connection->sent()[0]);
        self::assertInstanceOf(Publish::class, $decoded);
        self::assertSame($channel, $decoded->channel);
    }

    private function invokeHeartbeat(Hub $hub): void
    {
        $timer = (new ReflectionClass($hub))->getProperty('heartbeatTimer');
        $timer->setAccessible(true);
        $instance = $timer->getValue($hub);
        self::assertInstanceOf(TimerInterface::class, $instance);
        ($instance->getCallback())();
    }

    private function invokeHeartbeatTimerCancelled(Hub $hub): void
    {
        $timer = (new ReflectionClass($hub))->getProperty('heartbeatTimer');
        $timer->setAccessible(true);
        self::assertNull($timer->getValue($hub));
    }

    #[Test]
    public function sendReturnsTrueWhenConnectionSucceeds(): void
    {
        $hub     = $this->hub();
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $send = (new ReflectionClass(Hub::class))->getMethod('send');
        $send->setAccessible(true);

        self::assertTrue($send->invoke($hub, new SuccessfulSendConnection($this->watchStream(new ThroughStream()), $deflate), 'payload'));
    }

    #[Test]
    public function sendReturnsFalseWhenConnectionThrows(): void
    {
        $hub     = $this->hub();
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $send = (new ReflectionClass(Hub::class))->getMethod('send');
        $send->setAccessible(true);

        self::assertFalse($send->invoke($hub, new FailingSendConnection($this->watchStream(new ThroughStream()), $deflate), 'payload'));
    }

    private function shutdownConnectionStream(Connection $connection): void
    {
        $streamProperty = (new ReflectionClass(Connection::class))->getProperty('stream');
        $streamProperty->setAccessible(true);
        $stream = $streamProperty->getValue($connection);
        if (! ($stream instanceof DuplexStreamInterface)) {
            return;
        }

        $stream->close();
    }

    private function attach(Hub $hub, RecordingConnection $connection): RecordingConnection
    {
        $this->trackHubConnection($hub, $connection);

        return $connection;
    }

    private function attachConnection(Hub $hub, Connection $connection): Connection
    {
        $this->trackHubConnection($hub, $connection);

        return $connection;
    }

    private function trackHubConnection(Hub $hub, Connection $connection): void
    {
        $hub->onConnect($connection, new ServerRequest('GET', '/'));
        $this->connectionsToClose[] = $connection;
        if (in_array($hub, $this->hubsToShutdown, true)) {
            return;
        }

        $this->hubsToShutdown[] = $hub;
    }

    private function connection(): RecordingConnection
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);

        return new RecordingConnection($this->watchStream(new ThroughStream()), $deflate);
    }

    private function watchStream(ThroughStream $stream): ThroughStream
    {
        $this->streamsToClose[] = $stream;

        return $stream;
    }

    private function hub(): Hub
    {
        return new Hub(new PayloadCodec(new FrontendHydrator()), Defaults::HEARTBEAT_INTERVAL_SECONDS, Defaults::HEARTBEAT_CHANNEL);
    }
}
