<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Observability\OTel;

use App\Infrastructure\Observability\OTel\OTelSpanContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class OTelSpanContextTest extends TestCase
{
    #[Test]
    public function returnsTraceIdAndSpanId(): void
    {
        $context = new OTelSpanContext('abc123', 'span456');

        $this->assertSame('abc123', $context->getTraceId());
        $this->assertSame('span456', $context->getSpanId());
    }

    #[Test]
    public function getBaggageItemReturnsNullWhenMissing(): void
    {
        $context = new OTelSpanContext('t', 's');

        $this->assertNull($context->getBaggageItem('nonexistent'));
    }

    #[Test]
    public function getBaggageItemReturnsValueWhenPresent(): void
    {
        $context = new OTelSpanContext('t', 's', ['key' => 'value']);

        $this->assertSame('value', $context->getBaggageItem('key'));
    }

    #[Test]
    public function withBaggageItemReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $original = new OTelSpanContext('t', 's', ['a' => '1']);
        $modified = $original->withBaggageItem('b', '2');

        $this->assertNull($original->getBaggageItem('b'));
        $this->assertSame('2', $modified->getBaggageItem('b'));
        $this->assertSame('1', $modified->getBaggageItem('a'));
    }

    #[Test]
    public function withBaggageItemPreservesTraceIdAndSpanId(): void
    {
        $original = new OTelSpanContext('trace-1', 'span-2');
        $modified = $original->withBaggageItem('key', 'value');

        /* @var OTelSpanContext $modified */
        $this->assertSame('trace-1', $modified->getTraceId());
        $this->assertSame('span-2', $modified->getSpanId());
    }

    #[Test]
    public function getIteratorReturnsBaggageItems(): void
    {
        $baggage = ['k1' => 'v1', 'k2' => 'v2'];
        $context = new OTelSpanContext('t', 's', $baggage);

        $items = iterator_to_array($context->getIterator());

        $this->assertSame($baggage, $items);
    }

    #[Test]
    public function getIteratorReturnsEmptyWhenNoBaggage(): void
    {
        $context = new OTelSpanContext('t', 's');

        $this->assertEmpty(iterator_to_array($context->getIterator()));
    }
}
