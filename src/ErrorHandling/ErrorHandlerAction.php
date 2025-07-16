<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling;

enum ErrorHandlerAction: string
{
    case RETRY = 'retry';
    case FALLBACK = 'fallback';
    case COMPENSATE = 'compensate';
    case FAIL = 'fail';
    case ABORT = 'abort';
}
