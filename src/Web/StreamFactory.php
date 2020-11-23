<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\Web;

use Ancarda\Psr7\StringStream\StringStream;
use Laminas\Diactoros\StreamFactory as LDStreamFactory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class StreamFactory extends LDStreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createStream(string $content = '') : StreamInterface
    {
        return new StringStream($content);
    }

}
