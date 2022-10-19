<?php

declare(strict_types=1);

namespace Mammatus\Http\Server;

use Mammatus\Http\Server\Generated\AbstractConfiguration;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use WyriHaximus\React\Http\Middleware\CustomRequestBodyParsers;
use WyriHaximus\React\Http\Middleware\ResumeResponseBodyMiddleware;

use function Safe\ini_get;

final class Configuration extends AbstractConfiguration
{
    /**
     * @inheritDoc
     */
    final protected function middleware(): iterable
    {
        yield new ResumeResponseBodyMiddleware();
        yield new RequestBodyBufferMiddleware();

        if (ini_get('enable_post_data_reading') === '') {
            return;
        }

        yield new RequestBodyParserMiddleware();
        yield new CustomRequestBodyParsers();
    }
}
