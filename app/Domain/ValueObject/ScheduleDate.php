<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Enum\Timezone;
use App\Domain\Exception\InvalidScheduleDateException;
use DateTimeImmutable;

final readonly class ScheduleDate
{
    private function __construct(private DateTimeImmutable $date)
    {
    }

    public static function fromString(string $schedule): self
    {
        $clientTz = Timezone::CLIENT->toDateTimeZone();
        $storageTz = Timezone::STORAGE->toDateTimeZone();

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i', $schedule, $clientTz);

        if ($date === false) {
            throw InvalidScheduleDateException::invalidFormat($schedule);
        }

        $date = $date->setTimezone($storageTz);

        if ($date <= new DateTimeImmutable('now', $storageTz)) {
            throw InvalidScheduleDateException::inThePast();
        }

        return new self($date);
    }

    public static function fromDateTimeImmutable(DateTimeImmutable $date): self
    {
        return new self($date);
    }

    public function toDateTimeImmutable(): DateTimeImmutable
    {
        return $this->date;
    }

    public function toClientString(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->date
            ->setTimezone(Timezone::CLIENT->toDateTimeZone())
            ->format($format);
    }
}
