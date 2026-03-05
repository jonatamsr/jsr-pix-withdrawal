<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Entity\Account;
use App\Domain\Entity\AccountWithdraw;
use DateTimeImmutable;

final readonly class WithdrawCompleted implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private AccountWithdraw $withdraw,
        private Account $account
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function withdraw(): AccountWithdraw
    {
        return $this->withdraw;
    }

    public function account(): Account
    {
        return $this->account;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
