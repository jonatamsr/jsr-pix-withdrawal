<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Domain\Event\WithdrawFailed;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Log\LoggerInterface;

#[Listener]
class LogWithdrawFailedListener implements ListenerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function listen(): array
    {
        return [
            WithdrawFailed::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof WithdrawFailed) {
            return;
        }

        $withdraw = $event->withdraw();

        $this->logger->warning('Withdraw failed', [
            'withdraw_id' => $withdraw->id()->value(),
            'account_id' => $withdraw->accountId()->value(),
            'method' => $withdraw->method()->value,
            'amount' => $withdraw->amount()->toDecimal(),
            'reason' => $event->reason(),
            'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
