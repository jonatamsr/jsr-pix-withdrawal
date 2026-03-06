<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability;

use App\Infrastructure\Observability\OTel\OTelSpanContext;
use App\Infrastructure\Observability\TraceContextProcessor;
use DateTimeImmutable;
use Hyperf\Context\Context;
use Hyperf\Tracer\TracerContext;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTracing\Span;
use OpenTracing\SpanContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TraceContextProcessorTest extends TestCase
{
    use UsesMockery;

    private TraceContextProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new TraceContextProcessor();
    }

    protected function tearDown(): void
    {
        Context::destroy('request_id');
        Context::destroy('trace_id');
        Context::destroy(TracerContext::TRACE_ID);
        Context::destroy(TracerContext::ROOT);
    }

    #[Test]
    public function injectsRequestIdAndTraceIdFromContext(): void
    {
        Context::set('request_id', 'req-abc-123');
        Context::set('trace_id', 'trace-xyz-789');

        $record = $this->createLogRecord();
        $result = ($this->processor)($record);

        $this->assertSame('req-abc-123', $result->extra['request_id']);
        $this->assertSame('trace-xyz-789', $result->extra['trace_id']);
    }

    #[Test]
    public function returnsEmptyStringsWhenContextIsMissing(): void
    {
        $record = $this->createLogRecord();
        $result = ($this->processor)($record);

        $this->assertSame('', $result->extra['request_id']);
        $this->assertSame('', $result->extra['trace_id']);
    }

    #[Test]
    public function preservesExistingExtraFields(): void
    {
        Context::set('request_id', 'req-111');
        Context::set('trace_id', 'trace-222');

        $record = $this->createLogRecord(['existing_key' => 'existing_value']);
        $result = ($this->processor)($record);

        $this->assertSame('existing_value', $result->extra['existing_key']);
        $this->assertSame('req-111', $result->extra['request_id']);
        $this->assertSame('trace-222', $result->extra['trace_id']);
    }

    #[Test]
    public function extractsTraceIdFromTracerContext(): void
    {
        TracerContext::setTraceId('tracer-context-trace-id');

        $record = $this->createLogRecord();
        $result = ($this->processor)($record);

        $this->assertSame('tracer-context-trace-id', $result->extra['trace_id']);
    }

    #[Test]
    public function extractsTraceIdFromOTelSpanContextWhenNoTracerContextTraceId(): void
    {
        $otelSpanContext = new OTelSpanContext('otel-trace-id-123', 'span-456');

        $span = Mockery::mock(Span::class);
        $span->shouldReceive('getContext')->andReturn($otelSpanContext);

        TracerContext::setRoot($span);

        $record = $this->createLogRecord();
        $result = ($this->processor)($record);

        $this->assertSame('otel-trace-id-123', $result->extra['trace_id']);
    }

    #[Test]
    public function extractsTraceIdFromBaggageWhenNotOTelSpanContext(): void
    {
        $spanContext = Mockery::mock(SpanContext::class);
        $spanContext->shouldReceive('getBaggageItem')
            ->with('trace_id')
            ->andReturn('baggage-trace-id');

        $span = Mockery::mock(Span::class);
        $span->shouldReceive('getContext')->andReturn($spanContext);

        TracerContext::setRoot($span);

        $record = $this->createLogRecord();
        $result = ($this->processor)($record);

        $this->assertSame('baggage-trace-id', $result->extra['trace_id']);
    }

    #[Test]
    public function fallsBackToContextTraceIdWhenNoBaggageItem(): void
    {
        $spanContext = Mockery::mock(SpanContext::class);
        $spanContext->shouldReceive('getBaggageItem')
            ->with('trace_id')
            ->andReturn(null);

        $span = Mockery::mock(Span::class);
        $span->shouldReceive('getContext')->andReturn($spanContext);

        TracerContext::setRoot($span);

        Context::set('trace_id', 'fallback-trace-id');

        $record = $this->createLogRecord();
        $result = ($this->processor)($record);

        $this->assertSame('fallback-trace-id', $result->extra['trace_id']);
    }

    private function createLogRecord(array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            extra: $extra,
        );
    }
}
