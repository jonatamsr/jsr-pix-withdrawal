<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\OTel;

use DateTimeInterface;
use OpenTelemetry\API\Trace\SpanInterface as OTelSpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTracing\Span;
use OpenTracing\SpanContext;

final class OTelSpan implements Span
{
    private readonly OTelSpanContext $context;

    public function __construct(
        private readonly OTelSpanInterface $span,
        private string $operationName,
    ) {
        $otelContext = $this->span->getContext();

        $this->context = new OTelSpanContext(
            $otelContext->getTraceId(),
            $otelContext->getSpanId(),
        );
    }

    public function getOTelSpan(): OTelSpanInterface
    {
        return $this->span;
    }

    public function getOperationName(): string
    {
        return $this->operationName;
    }

    public function getContext(): SpanContext
    {
        return $this->context;
    }

    public function finish($finishTime = null): void
    {
        $endNanos = null;

        if ($finishTime instanceof DateTimeInterface) {
            $endNanos = (int) ($finishTime->format('U.u') * 1_000_000_000);
        } elseif (is_float($finishTime) || is_int($finishTime)) {
            $endNanos = (int) ($finishTime * 1_000_000_000);
        }

        $this->span->end($endNanos);
    }

    public function overwriteOperationName(string $newOperationName): void
    {
        $this->operationName = $newOperationName;
        $this->span->updateName($newOperationName);
    }

    public function setTag(string $key, $value): void
    {
        if ($key === 'error' && $value === true) {
            $this->span->setStatus(StatusCode::STATUS_ERROR);
        }

        $this->span->setAttribute($key, $value);
    }

    public function log(array $fields = [], $timestamp = null): void
    {
        $this->span->addEvent('log', $fields);
    }

    public function addBaggageItem(string $key, string $value): void
    {
        // Baggage is not natively supported in OTel spans the same way;
        // stored in the span context wrapper for compatibility.
    }

    public function getBaggageItem(string $key): ?string
    {
        return $this->context->getBaggageItem($key);
    }
}
