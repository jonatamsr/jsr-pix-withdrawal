<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability\OTel;

use App\Infrastructure\Observability\OTel\OTelSpan;
use App\Infrastructure\Observability\OTel\OTelSpanContext;
use App\Infrastructure\Observability\OTel\OTelTracer;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface as OTelSpanInterface;
use OpenTelemetry\API\Trace\TracerInterface as OTelTracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const OpenTracing\Formats\TEXT_MAP;

/**
 * @internal
 */
class OTelTracerTest extends TestCase
{
    use UsesMockery;

    private TracerProviderInterface|MockInterface $provider;

    private OTelTracerInterface|MockInterface $otelTracer;

    private OTelTracer $tracer;

    protected function setUp(): void
    {
        $this->otelTracer = Mockery::mock(OTelTracerInterface::class);
        $this->provider = Mockery::mock(TracerProviderInterface::class);
        $this->provider->shouldReceive('getTracer')->with('test-service')->andReturn($this->otelTracer);

        $this->tracer = new OTelTracer($this->provider, 'test-service');
    }

    #[Test]
    public function getScopeManagerReturnsManager(): void
    {
        $this->assertNotNull($this->tracer->getScopeManager());
    }

    #[Test]
    public function getActiveSpanReturnsNullWhenNoActiveScope(): void
    {
        $this->assertNull($this->tracer->getActiveSpan());
    }

    #[Test]
    public function startSpanReturnsOTelSpanWrapper(): void
    {
        $otelSpanContext = Mockery::mock(SpanContextInterface::class);
        $otelSpanContext->shouldReceive('getTraceId')->andReturn('trace-123');
        $otelSpanContext->shouldReceive('getSpanId')->andReturn('span-456');

        $otelSpan = Mockery::mock(OTelSpanInterface::class);
        $otelSpan->shouldReceive('getContext')->andReturn($otelSpanContext);

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($otelSpan);

        $this->otelTracer->shouldReceive('spanBuilder')->with('my.span')->andReturn($spanBuilder);

        $span = $this->tracer->startSpan('my.span');

        $this->assertInstanceOf(OTelSpan::class, $span);
        $this->assertSame('my.span', $span->getOperationName());
    }

    #[Test]
    public function startActiveSpanActivatesScope(): void
    {
        $otelSpanContext = Mockery::mock(SpanContextInterface::class);
        $otelSpanContext->shouldReceive('getTraceId')->andReturn('trace-1');
        $otelSpanContext->shouldReceive('getSpanId')->andReturn('span-1');

        $otelSpan = Mockery::mock(OTelSpanInterface::class);
        $otelSpan->shouldReceive('getContext')->andReturn($otelSpanContext);
        $otelSpan->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($otelSpan);

        $this->otelTracer->shouldReceive('spanBuilder')->andReturn($spanBuilder);

        $scope = $this->tracer->startActiveSpan('test.span');

        $this->assertNotNull($this->tracer->getActiveSpan());
        $this->assertSame($scope->getSpan(), $this->tracer->getActiveSpan());

        $scope->close();

        $this->assertNull($this->tracer->getActiveSpan());
    }

    #[Test]
    public function startSpanWithTagsSetsAttributes(): void
    {
        $otelSpanContext = Mockery::mock(SpanContextInterface::class);
        $otelSpanContext->shouldReceive('getTraceId')->andReturn('t');
        $otelSpanContext->shouldReceive('getSpanId')->andReturn('s');

        $otelSpan = Mockery::mock(OTelSpanInterface::class);
        $otelSpan->shouldReceive('getContext')->andReturn($otelSpanContext);
        $otelSpan->shouldReceive('setAttribute')->with('http.method', 'POST')->once();

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($otelSpan);

        $this->otelTracer->shouldReceive('spanBuilder')->andReturn($spanBuilder);

        $this->tracer->startSpan('tagged.span', ['tags' => ['http.method' => 'POST']]);
    }

    #[Test]
    public function injectWritesTraceparentToCarrier(): void
    {
        $context = new OTelSpanContext('abc123def456', 'span789');
        $carrier = [];

        $this->tracer->inject($context, TEXT_MAP, $carrier);

        $this->assertArrayHasKey('traceparent', $carrier);
        $this->assertSame('00-abc123def456-span789-01', $carrier['traceparent']);
    }

    #[Test]
    public function injectIgnoresNonTextMapFormat(): void
    {
        $context = new OTelSpanContext('t', 's');
        $carrier = [];

        $this->tracer->inject($context, 'unsupported_format', $carrier);

        $this->assertEmpty($carrier);
    }

    #[Test]
    public function extractReturnsContextFromTraceparent(): void
    {
        $carrier = ['traceparent' => '00-abcdef1234567890-1234abcd5678-01'];

        $context = $this->tracer->extract(TEXT_MAP, $carrier);

        $this->assertInstanceOf(OTelSpanContext::class, $context);
        /** @var OTelSpanContext $context */
        $this->assertSame('abcdef1234567890', $context->getTraceId());
        $this->assertSame('1234abcd5678', $context->getSpanId());
    }

    #[Test]
    public function extractReturnsNullWhenNoTraceparent(): void
    {
        $this->assertNull($this->tracer->extract(TEXT_MAP, []));
    }

    #[Test]
    public function extractReturnsNullForNonTextMapFormat(): void
    {
        $this->assertNull($this->tracer->extract('unsupported', ['traceparent' => '00-a-b-01']));
    }

    #[Test]
    public function extractReturnsNullForNonArrayCarrier(): void
    {
        $this->assertNull($this->tracer->extract(TEXT_MAP, 'not-an-array'));
    }

    #[Test]
    public function extractReturnsNullForMalformedTraceparent(): void
    {
        $this->assertNull($this->tracer->extract(TEXT_MAP, ['traceparent' => 'invalid']));
    }

    #[Test]
    public function flushDelegatesToTracerProvider(): void
    {
        $this->provider->shouldReceive('forceFlush')->once();

        $this->tracer->flush();
    }
}
