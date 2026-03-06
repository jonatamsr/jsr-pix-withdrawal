<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Strategy\WithdrawMethodData;
use App\Domain\ValueObject\PendingWithdrawal;

interface WithdrawRepositoryInterface
{
    public function save(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData = null): void;

    /**
     * @return PendingWithdrawal[]
     */
    public function findPendingScheduled(): array;
}
