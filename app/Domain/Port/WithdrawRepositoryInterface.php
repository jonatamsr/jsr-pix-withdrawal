<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;

interface WithdrawRepositoryInterface
{
    public function save(AccountWithdraw $withdraw, ?AccountWithdrawPix $pix = null): void;

    /**
     * @return AccountWithdraw[]
     */
    public function findPendingScheduled(): array;
}
