<?php

declare(strict_types=1);

namespace Mammatus\Http\Server;

use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;
use WyriHaximus\React\Stream\Json\JsonStream;

use const WyriHaximus\Constants\HTTPStatusCodes\OK;

final class JsonResponse
{
    /**
     * @param int        $status  Status code for the response, if any.
     * @param string[]   $headers Headers for the response, if any.
     * @param JsonStream $body    Stream body.
     * @param string     $version Protocol version.
     * @param string     $reason  Reason phrase (a default will be used if possible).
     */
    public static function create(
        JsonStream $body,
        int $status = OK,
        array $headers = [],
        string $version = '1.1',
        string $reason = ''
    ): ResponseInterface {
        return new Response(
            $status,
            $headers,
            $body,
            $version,
            $reason !== '' ? $reason : null
        );
    }
}
