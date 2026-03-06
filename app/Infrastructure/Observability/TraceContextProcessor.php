<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Hyperf\Context\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TraceContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['request_id'] = Context::get('request_id', '');
        $record->extra['trace_id'] = Context::get('trace_id', '');

        return $record;
    }
}
