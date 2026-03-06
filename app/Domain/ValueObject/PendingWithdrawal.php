<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Strategy\WithdrawMethodData;

final readonly class PendingWithdrawal
{
    public function __construct(
        private AccountWithdraw $withdraw,
        private ?WithdrawMethodData $methodData = null,
    ) {
    }

    public function withdraw(): AccountWithdraw
    {
        return $this->withdraw;
    }

    public function methodData(): ?WithdrawMethodData
    {
        return $this->methodData;
    }
}
