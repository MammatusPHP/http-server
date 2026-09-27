<?php

declare(strict_types=1);

namespace Mammatus\Http\Server\WebSocket\Protocol;

enum WireProperties: string
{
    case OPERATION = 'o';
    case ID        = 'i';
    case METHOD    = 'm';
    case CHANNEL   = 'c';
    case PAYLOAD   = 'p';
    case RESULT    = 'r';
    case ERROR     = 'e';
    case DATA      = 'd';
    case MESSAGE   = 'g';
}
