<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket;

use Evenement\EventEmitter;
use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\Stream\DuplexStreamInterface;

use function is_string;
use function pack;

class Connection extends EventEmitter
{
    private bool $closed = false;

    private MessageBuffer $messageBuffer;

    public function __construct(
        private DuplexStreamInterface $stream,
        PermessageDeflateOptions $permessageDeflateOptions,
    ) {
        $stream              = $this->stream;
        $self                = $this;
        $this->messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            static function (Message $message) use ($self): void {
                $payload = $message->getPayload();
                if (! is_string($payload)) {
                    return;
                }

                $self->emit('message', [$payload, $self]);
            },
            static function (Frame $frame) use ($stream): void {
                if ($frame->getOpcode() === Frame::OP_PING) {
                    $stream->write(
                        (new Frame($frame->getPayload(), true, Frame::OP_PONG))->getContents(),
                    );
                } elseif ($frame->getOpcode() === Frame::OP_CLOSE) {
                    $stream->write($frame->getContents());
                }
            },
            true,
            null,
            1024 * 1024,
            1024 * 1024,
            [$stream, 'write'],
            $permessageDeflateOptions,
        );

        $buffer = $this->messageBuffer;
        $stream->on('data', [$buffer, 'onData']);
        $stream->on('close', static function () use ($self): void {
            $self->emitClose();
        });
        $stream->on('error', static function () use ($self): void {
            $self->emit('error', [$self]);
        });
    }

    private function emitClose(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $self         = $this;
        $this->emit('close', [$self]);
    }

    public function send(string|Frame $data): void
    {
        $buffer = $this->messageBuffer;
        if ($data instanceof Frame) {
            $buffer->sendFrame($data);

            return;
        }

        $buffer->sendMessage($data);
    }

    public function close(): void
    {
        $stream = $this->stream;
        $stream->end((new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->getContents());
    }
}
