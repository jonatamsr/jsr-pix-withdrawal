<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Strategy\WithdrawMethodData;

interface WithdrawRepositoryInterface
{
    public function save(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData = null): void;

    /**
     * @return AccountWithdraw[]
     */
    public function findPendingScheduled(): array;
}
