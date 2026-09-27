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
        private readonly DuplexStreamInterface $stream,
        PermessageDeflateOptions $permessageDeflateOptions,
    ) {
        $this->messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            function (Message $message): void {
                $payload = $message->getPayload();
                if (! is_string($payload)) {
                    return;
                }

                $this->emit('message', [$payload, $this]);
            },
            function (Frame $frame): void {
                if ($frame->getOpcode() === Frame::OP_PING) {
                    $this->stream->write(
                        (new Frame($frame->getPayload(), true, Frame::OP_PONG))->getContents(),
                    );
                } elseif ($frame->getOpcode() === Frame::OP_CLOSE) {
                    $this->stream->end($frame->getContents());
                }
            },
            true,
            null,
            1024 * 1024,
            1024 * 1024,
            [$this->stream, 'write'],
            $permessageDeflateOptions,
        );

        $stream->on('data', [$this->messageBuffer, 'onData']);
        $stream->on('close', function (): void {
            $this->emitClose();
        });
        $stream->on('error', function (): void {
            $this->emit('error', [$this]);
        });
    }

    private function emitClose(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('close', [$this]);
    }

    public function send(string|Frame $data): void
    {
        if ($data instanceof Frame) {
            $this->messageBuffer->sendFrame($data);

            return;
        }

        $this->messageBuffer->sendMessage($data);
    }

    public function close(): void
    {
        $this->stream->end((new Frame(pack('n', 1000), true, Frame::OP_CLOSE))->getContents());
    }
}
