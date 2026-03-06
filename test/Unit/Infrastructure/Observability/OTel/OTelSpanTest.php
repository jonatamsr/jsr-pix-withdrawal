<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability\OTel;

use App\Infrastructure\Observability\OTel\OTelSpan;
use App\Infrastructure\Observability\OTel\OTelSpanContext;
use DateTimeImmutable;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface as OTelSpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OTelSpanTest extends TestCase
{
    use UsesMockery;

    private OTelSpanInterface|MockInterface $otelSpan;

    private OTelSpan $span;

    protected function setUp(): void
    {
        $spanContext = Mockery::mock(SpanContextInterface::class);
        $spanContext->shouldReceive('getTraceId')->andReturn('trace-abc');
        $spanContext->shouldReceive('getSpanId')->andReturn('span-def');

        $this->otelSpan = Mockery::mock(OTelSpanInterface::class);
        $this->otelSpan->shouldReceive('getContext')->andReturn($spanContext);

        $this->span = new OTelSpan($this->otelSpan, 'test.operation');
    }

    #[Test]
    public function returnsOperationName(): void
    {
        $this->assertSame('test.operation', $this->span->getOperationName());
    }

    #[Test]
    public function returnsOTelSpanContextWrapped(): void
    {
        $context = $this->span->getContext();

        $this->assertInstanceOf(OTelSpanContext::class, $context);
        /** @var OTelSpanContext $context */
        $this->assertSame('trace-abc', $context->getTraceId());
        $this->assertSame('span-def', $context->getSpanId());
    }

    #[Test]
    public function getOTelSpanReturnsUnderlyingSpan(): void
    {
        $this->assertSame($this->otelSpan, $this->span->getOTelSpan());
    }

    #[Test]
    public function finishCallsEndWithNull(): void
    {
        $this->otelSpan->shouldReceive('end')->once()->with(null);

        $this->span->finish();
    }

    #[Test]
    public function finishWithFloatConvertsToNanos(): void
    {
        $this->otelSpan->shouldReceive('end')
            ->once()
            ->with(Mockery::on(fn(int $nanos) => $nanos > 0));

        $this->span->finish(1609459200.5);
    }

    #[Test]
    public function finishWithIntConvertsToNanos(): void
    {
        $this->otelSpan->shouldReceive('end')
            ->once()
            ->with(Mockery::on(fn(int $nanos) => $nanos === 1_000_000_000));

        $this->span->finish(1);
    }

    #[Test]
    public function finishWithDateTimeConvertsToNanos(): void
    {
        $dt = new DateTimeImmutable('2021-01-01 00:00:00');

        $this->otelSpan->shouldReceive('end')
            ->once()
            ->with(Mockery::on(fn(int $nanos) => $nanos > 0));

        $this->span->finish($dt);
    }

    #[Test]
    public function overwriteOperationNameUpdatesNameAndSpan(): void
    {
        $this->otelSpan->shouldReceive('updateName')->once()->with('new.name');

        $this->span->overwriteOperationName('new.name');

        $this->assertSame('new.name', $this->span->getOperationName());
    }

    #[Test]
    public function setTagDelegatesToSetAttribute(): void
    {
        $this->otelSpan->shouldReceive('setAttribute')->once()->with('http.method', 'GET');

        $this->span->setTag('http.method', 'GET');
    }

    #[Test]
    public function setTagErrorTrueSetsStatusError(): void
    {
        $this->otelSpan->shouldReceive('setStatus')->once()->with(StatusCode::STATUS_ERROR);
        $this->otelSpan->shouldReceive('setAttribute')->once()->with('error', true);

        $this->span->setTag('error', true);
    }

    #[Test]
    public function setTagErrorStringDoesNotSetStatus(): void
    {
        $this->otelSpan->shouldNotReceive('setStatus');
        $this->otelSpan->shouldReceive('setAttribute')->once()->with('error', 'some message');

        $this->span->setTag('error', 'some message');
    }

    #[Test]
    public function logAddsEventToSpan(): void
    {
        $fields = ['event' => 'something', 'message' => 'details'];

        $this->otelSpan->shouldReceive('addEvent')->once()->with('log', $fields);

        $this->span->log($fields);
    }

    #[Test]
    public function getBaggageItemDelegatesToContext(): void
    {
        $this->assertNull($this->span->getBaggageItem('missing'));
    }
}
