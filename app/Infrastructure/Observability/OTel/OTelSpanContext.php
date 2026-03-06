<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\OTel;

use ArrayIterator;
use OpenTracing\SpanContext;
use Traversable;

final class OTelSpanContext implements SpanContext
{
    /** @param array<string, string> $baggage */
    public function __construct(
        private readonly string $traceId,
        private readonly string $spanId,
        private array $baggage = [],
    ) {
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getBaggageItem(string $key): ?string
    {
        return $this->baggage[$key] ?? null;
    }

    public function withBaggageItem(string $key, string $value): SpanContext
    {
        $clone = clone $this;
        $clone->baggage[$key] = $value;

        return $clone;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->baggage);
    }
}
