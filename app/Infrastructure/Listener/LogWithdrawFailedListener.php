<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Domain\Event\WithdrawFailed;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

#[Listener]
class LogWithdrawFailedListener implements ListenerInterface
{
    public function __construct(
        private readonly StdoutLoggerInterface $logger,
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

        $this->logger->error('Withdraw failed', [
            'withdraw_id' => $withdraw->id()->value(),
            'account_id' => $withdraw->accountId()->value(),
            'method' => $withdraw->method()->value,
            'amount' => $withdraw->amount()->toDecimal(),
            'reason' => $event->reason(),
            'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
