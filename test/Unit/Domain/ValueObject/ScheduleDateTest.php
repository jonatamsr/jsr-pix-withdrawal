<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidScheduleDateException;
use App\Domain\ValueObject\ScheduleDate;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ScheduleDateTest extends TestCase
{
    #[Test]
    public function fromStringParsesValidFutureDate(): void
    {
        $schedule = ScheduleDate::fromString('2027-06-15 10:00');

        $date = $schedule->toDateTimeImmutable();

        $this->assertSame('UTC', $date->getTimezone()->getName());
        $this->assertSame('2027-06-15 13:00', $date->format('Y-m-d H:i'));
    }

    #[Test]
    public function fromStringThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidScheduleDateException::class);
        $this->expectExceptionMessage('Invalid schedule date format: not-a-date');

        ScheduleDate::fromString('not-a-date');
    }

    #[Test]
    public function fromStringThrowsOnPastDate(): void
    {
        $this->expectException(InvalidScheduleDateException::class);
        $this->expectExceptionMessage('The schedule date must be in the future');

        ScheduleDate::fromString('2020-01-01 10:00');
    }

    #[Test]
    public function fromDateTimeImmutableWrapsGivenDate(): void
    {
        $original = new DateTimeImmutable('2027-06-15 13:00:00', new DateTimeZone('UTC'));

        $schedule = ScheduleDate::fromDateTimeImmutable($original);

        $this->assertSame($original, $schedule->toDateTimeImmutable());
    }

    #[Test]
    public function toClientStringConvertsToSaoPauloTimezone(): void
    {
        $utcDate = new DateTimeImmutable('2027-06-15 13:00:00', new DateTimeZone('UTC'));

        $schedule = ScheduleDate::fromDateTimeImmutable($utcDate);

        $this->assertSame('2027-06-15 10:00:00', $schedule->toClientString());
    }

    #[Test]
    public function toClientStringAcceptsCustomFormat(): void
    {
        $utcDate = new DateTimeImmutable('2027-06-15 13:00:00', new DateTimeZone('UTC'));

        $schedule = ScheduleDate::fromDateTimeImmutable($utcDate);

        $this->assertSame('2027-06-15 10:00', $schedule->toClientString('Y-m-d H:i'));
    }

    #[Test]
    public function fromStringConvertsClientTimezoneToUtc(): void
    {
        $schedule = ScheduleDate::fromString('2027-12-25 09:30');

        $date = $schedule->toDateTimeImmutable();

        $this->assertSame('UTC', $date->getTimezone()->getName());
        $this->assertSame('2027-12-25 12:30', $date->format('Y-m-d H:i'));
    }
}
