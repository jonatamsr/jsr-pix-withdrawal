<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\Strategy\WithdrawMethodData;
use App\Infrastructure\Mail\SymfonyMailerService;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class PixWithdrawNotificationStrategy implements WithdrawNotificationStrategyInterface
{
    public function __construct(
        private readonly SymfonyMailerService $mailerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData, DateTimeImmutable $processedAt): void
    {
        if (! $methodData instanceof PixWithdrawData) {
            $this->logger->warning('PIX data not found for withdraw notification', [
                'withdraw_id' => $withdraw->id()->value(),
            ]);

            return;
        }

        $pix = AccountWithdrawPix::create($withdraw->id(), $methodData->getPixKey());

        $this->mailerService->sendWithdrawCompleted($withdraw, $pix, $processedAt);

        $this->logger->info('Withdraw notification email sent', [
            'withdraw_id' => $withdraw->id()->value(),
            'pix_key' => $methodData->getPixKey()->key(),
        ]);
    }
}
