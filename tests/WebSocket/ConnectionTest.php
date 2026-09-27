<?php

declare(strict_types=1);

namespace Mammatus\Tests\Http\Server\WebSocket;

use Mammatus\Http\Server\WebSocket\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use Ratchet\RFC6455\Messaging\Frame;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;
use ReflectionMethod;
use RuntimeException;
use WyriHaximus\TestUtilities\TestCase;

use function pack;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
{
    #[Test]
    public function inboundTextPingAndOutboundSend(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $in         = new ThroughStream();
        $out        = new ThroughStream();
        $connection = new Connection(new CompositeStream($in, $out), $deflate);
        $messages   = [];
        $connection->on('message', static function (string $payload) use (&$messages): void {
            $messages[] = $payload;
        });

        $outWrites = '';
        $out->on('data', static function (string $chunk) use (&$outWrites): void {
            $outWrites .= $chunk;
        });

        $in->write((new Frame('inbound', true, Frame::OP_TEXT))->maskPayload()->getContents());
        self::assertSame(['inbound'], $messages);

        $in->write((new Frame('ping-body', true, Frame::OP_PING))->maskPayload()->getContents());
        self::assertSame(
            (new Frame('ping-body', true, Frame::OP_PONG))->getContents(),
            $outWrites,
        );

        $connection->send('payload');
        $connection->send(new Frame('', true, Frame::OP_PING));
    }

    #[Test]
    public function inboundCloseFrameEmitsCloseWithoutStreamEnd(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $in         = new ThroughStream();
        $connection = new Connection(new CompositeStream($in, new ThroughStream()), $deflate);
        $closed     = 0;
        $connection->on('close', static function (Connection $closedConnection) use (&$closed, $connection): void {
            self::assertSame($connection, $closedConnection);
            ++$closed;
        });

        $in->write((new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->maskPayload()->getContents());
        $in->close();

        self::assertSame(1, $closed);
    }

    #[Test]
    public function inboundCloseAndDoubleApplicationClose(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $in         = new ThroughStream();
        $out        = new ThroughStream();
        $connection = new Connection(new CompositeStream($in, $out), $deflate);
        $closed     = 0;
        $connection->on('close', static function (Connection $closedConnection) use (&$closed, $connection): void {
            self::assertSame($connection, $closedConnection);
            ++$closed;
        });

        $ended = '';
        $out->on('data', static function (string $chunk) use (&$ended): void {
            $ended .= $chunk;
        });

        $in->write((new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->maskPayload()->getContents());
        self::assertSame(
            (new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->getContents(),
            $ended,
        );

        $connection->close();
        $connection->close();
        $in->close();

        self::assertSame(1, $closed);
    }

    #[Test]
    public function applicationCloseSendsStatus1000(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $out        = new ThroughStream();
        $connection = new Connection(new CompositeStream(new ThroughStream(), $out), $deflate);
        $written    = '';
        $out->on('data', static function (string $chunk) use (&$written): void {
            $written .= $chunk;
        });

        $connection->close();

        self::assertSame(
            (new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->getContents(),
            $written,
        );
    }

    #[Test]
    public function streamCloseEmitsConnectionCloseOnce(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $in         = new ThroughStream();
        $connection = new Connection(new CompositeStream($in, new ThroughStream()), $deflate);
        $closed     = 0;
        $connection->on('close', static function (Connection $closedConnection) use (&$closed, $connection): void {
            self::assertSame($connection, $closedConnection);
            ++$closed;
        });

        $in->close();

        self::assertSame(1, $closed);
    }

    #[Test]
    public function emitCloseIsIdempotent(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $connection = new Connection(new CompositeStream(new ThroughStream(), new ThroughStream()), $deflate);
        $closed     = 0;
        $connection->on('close', static function () use (&$closed): void {
            ++$closed;
        });

        $emitClose = new ReflectionMethod(Connection::class, 'emitClose');
        $emitClose->setAccessible(true);
        $emitClose->invoke($connection);
        $emitClose->invoke($connection);

        self::assertSame(1, $closed);
    }

    #[Test]
    public function streamErrorIsForwarded(): void
    {
        $deflate = PermessageDeflateOptions::createDisabled();
        self::assertInstanceOf(PermessageDeflateOptions::class, $deflate);
        $in         = new ThroughStream();
        $connection = new Connection(new CompositeStream($in, new ThroughStream()), $deflate);
        $errors     = 0;
        $connection->on('error', static function (Connection $errored) use (&$errors, $connection): void {
            self::assertSame($connection, $errored);
            ++$errors;
        });

        $in->emit('error', [new RuntimeException('stream failed')]);

        self::assertSame(1, $errors);
    }
}
