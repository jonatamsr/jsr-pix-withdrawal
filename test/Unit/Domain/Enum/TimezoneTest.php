<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Enum;

use App\Domain\Enum\Timezone;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TimezoneTest extends TestCase
{
    #[Test]
    public function storageCaseHasUtcValue(): void
    {
        $this->assertSame('UTC', Timezone::STORAGE->value);
    }

    #[Test]
    public function clientCaseHasSaoPauloValue(): void
    {
        $this->assertSame('America/Sao_Paulo', Timezone::CLIENT->value);
    }

    #[Test]
    public function toDateTimeZoneReturnsCorrectInstanceForStorage(): void
    {
        $tz = Timezone::STORAGE->toDateTimeZone();

        $this->assertInstanceOf(DateTimeZone::class, $tz);
        $this->assertSame('UTC', $tz->getName());
    }

    #[Test]
    public function toDateTimeZoneReturnsCorrectInstanceForClient(): void
    {
        $tz = Timezone::CLIENT->toDateTimeZone();

        $this->assertInstanceOf(DateTimeZone::class, $tz);
        $this->assertSame('America/Sao_Paulo', $tz->getName());
    }
}
