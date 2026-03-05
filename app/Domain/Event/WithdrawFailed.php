<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Entity\AccountWithdraw;
use DateTimeImmutable;

final readonly class WithdrawFailed implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private AccountWithdraw $withdraw,
        private string $reason
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function withdraw(): AccountWithdraw
    {
        return $this->withdraw;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
