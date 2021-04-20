<?php

declare(strict_types=1);

namespace Mammatus\Http\Server;

use Mammatus\Http\Server\Generated\AbstractConfiguration;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use WyriHaximus\React\Http\Middleware\CustomRequestBodyParsers;
use WyriHaximus\React\Http\Middleware\ResumeResponseBodyMiddleware;

use function ini_get;

final class Configuration extends AbstractConfiguration
{
    private LoopInterface $loop;
    private LoggerInterface $logger;

    public function __construct(LoopInterface $loop, LoggerInterface $logger, ContainerInterface $container)
    {
        $this->loop   = $loop;
        $this->logger = $logger;

        $this->initialize($loop, $logger, $container);
    }

    protected function middleware(): iterable
    {
        yield new ResumeResponseBodyMiddleware($this->loop);
        yield new RequestBodyBufferMiddleware();

        if (ini_get('enable_post_data_reading') === '') {
            return;
        }

        yield new RequestBodyParserMiddleware();
        yield new CustomRequestBodyParsers();
    }
}
