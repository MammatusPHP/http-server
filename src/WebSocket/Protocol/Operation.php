<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol;

enum Operation: string
{
    case CALL        = 'rpc';
    case RESULT      = 'res';
    case ERROR       = 'err';
    case SUBSCRIBE   = 'sub';
    case UNSUBSCRIBE = 'unsub';
    case PUBLISH     = 'evt';
}
