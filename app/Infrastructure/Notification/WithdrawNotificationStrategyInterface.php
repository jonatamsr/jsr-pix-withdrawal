<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Strategy\WithdrawMethodData;
use DateTimeImmutable;

interface WithdrawNotificationStrategyInterface
{
    public function notify(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData, DateTimeImmutable $processedAt): void;
}
