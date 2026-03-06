<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\OTel;

use DateTimeInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface as OTelTracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTracing\Scope;
use OpenTracing\ScopeManager;
use OpenTracing\Span;
use OpenTracing\SpanContext;
use OpenTracing\StartSpanOptions;
use OpenTracing\Tracer;

use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_SERVER;

final class OTelTracer implements Tracer
{
    private readonly OTelTracerInterface $tracer;

    private readonly OTelScopeManager $scopeManager;

    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
        string $instrumentationName = 'jsr-pix-withdrawal',
    ) {
        $this->tracer = $this->tracerProvider->getTracer($instrumentationName);
        $this->scopeManager = new OTelScopeManager();
    }

    public function getScopeManager(): ScopeManager
    {
        return $this->scopeManager;
    }

    public function getActiveSpan(): ?Span
    {
        $scope = $this->scopeManager->getActive();

        return $scope?->getSpan();
    }

    public function startActiveSpan(string $operationName, $options = []): Scope
    {
        $otOptions = $this->resolveOptions($options);
        $span = $this->startSpan($operationName, $otOptions);

        return $this->scopeManager->activate($span, $otOptions->shouldFinishSpanOnClose());
    }

    public function startSpan(string $operationName, $options = []): Span
    {
        $otOptions = $this->resolveOptions($options);

        $spanBuilder = $this->tracer->spanBuilder($operationName);

        // Resolve parent context from references
        $parentOTelContext = null;
        foreach ($otOptions->getReferences() as $reference) {
            $refContext = $reference->getSpanContext();
            if ($refContext instanceof OTelSpanContext) {
                // Create an OTel context from the parent span context
                $parentSpanContext = \OpenTelemetry\API\Trace\SpanContext::createFromRemoteParent(
                    $refContext->getTraceId(),
                    $refContext->getSpanId(),
                );
                $parentOTelContext = Context::getRoot()->withContextValue(
                    \OpenTelemetry\API\Trace\Span::wrap($parentSpanContext),
                );
                break;
            }
        }

        // If no explicit parent, check if there's an active span
        if ($parentOTelContext === null && ! $otOptions->shouldIgnoreActiveSpan()) {
            $activeSpan = $this->getActiveSpan();
            if ($activeSpan instanceof OTelSpan) {
                $parentOTelContext = Context::getCurrent()->withContextValue($activeSpan->getOTelSpan());
            }
        }

        if ($parentOTelContext !== null) {
            $spanBuilder->setParent($parentOTelContext);
        }

        // Set span kind based on tags
        $tags = $otOptions->getTags();
        if (isset($tags[SPAN_KIND])) {
            $spanBuilder->setSpanKind(match ($tags[SPAN_KIND]) {
                SPAN_KIND_RPC_SERVER, 'server' => SpanKind::KIND_SERVER,
                'client' => SpanKind::KIND_CLIENT,
                'producer' => SpanKind::KIND_PRODUCER,
                'consumer' => SpanKind::KIND_CONSUMER,
                default => SpanKind::KIND_INTERNAL,
            });
        }

        // Set start time
        $startTime = $otOptions->getStartTime();
        if ($startTime !== null) {
            if ($startTime instanceof DateTimeInterface) {
                $nanos = (int) ($startTime->format('U.u') * 1_000_000_000);
            } else {
                $nanos = (int) ($startTime * 1_000_000_000);
            }
            $spanBuilder->setStartTimestamp($nanos);
        }

        $otelSpan = $spanBuilder->startSpan();
        $wrappedSpan = new OTelSpan($otelSpan, $operationName);

        // Set tags as attributes
        foreach ($tags as $key => $value) {
            if ($key === SPAN_KIND) {
                continue; // already handled
            }
            $wrappedSpan->setTag($key, $value);
        }

        return $wrappedSpan;
    }

    public function inject(SpanContext $spanContext, string $format, &$carrier): void
    {
        if ($format !== TEXT_MAP || ! $spanContext instanceof OTelSpanContext) {
            return;
        }

        $carrier['traceparent'] = sprintf(
            '00-%s-%s-01',
            $spanContext->getTraceId(),
            $spanContext->getSpanId(),
        );
    }

    public function extract(string $format, $carrier): ?SpanContext
    {
        if ($format !== TEXT_MAP || ! is_array($carrier)) {
            return null;
        }

        $traceparent = $carrier['traceparent'] ?? null;
        if ($traceparent === null) {
            return null;
        }

        $parts = explode('-', $traceparent);
        if (count($parts) < 3) {
            return null;
        }

        return new OTelSpanContext($parts[1], $parts[2]);
    }

    public function flush(): void
    {
        $this->tracerProvider->forceFlush();
    }

    private function resolveOptions(array|StartSpanOptions $options): StartSpanOptions
    {
        if ($options instanceof StartSpanOptions) {
            return $options;
        }

        return StartSpanOptions::create($options);
    }
}
