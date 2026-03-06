<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Strategy\WithdrawMethodData;

interface WithdrawNotificationStrategyInterface
{
    public function notify(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData): void;
}
