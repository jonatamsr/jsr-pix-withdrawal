<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability;

use App\Infrastructure\Observability\TraceContextProcessor;
use DateTimeImmutable;
use Hyperf\Context\Context;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TraceContextProcessorTest extends TestCase
{
    private TraceContextProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new TraceContextProcessor();
    }

    protected function tearDown(): void
    {
        Context::destroy('request_id');
        Context::destroy('trace_id');
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
