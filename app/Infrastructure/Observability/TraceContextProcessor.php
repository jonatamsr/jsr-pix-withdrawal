<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Infrastructure\Observability\OTel\OTelSpanContext;
use Hyperf\Context\Context;
use Hyperf\Tracer\TracerContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TraceContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['request_id'] = Context::get('request_id', '');

        $traceId = TracerContext::getTraceId();
        if (! $traceId) {
            $root = TracerContext::getRoot();
            if ($root !== null) {
                $spanContext = $root->getContext();
                $traceId = $spanContext instanceof OTelSpanContext
                    ? $spanContext->getTraceId()
                    : ($spanContext->getBaggageItem('trace_id') ?? '');
            }
        }

        $record->extra['trace_id'] = $traceId ?: Context::get('trace_id', '');

        return $record;
    }
}
