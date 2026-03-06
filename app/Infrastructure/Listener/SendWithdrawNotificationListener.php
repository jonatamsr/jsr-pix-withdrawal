<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Domain\Event\WithdrawCompleted;
use App\Infrastructure\Notification\WithdrawNotificationStrategyFactory;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

#[Listener]
class SendWithdrawNotificationListener implements ListenerInterface
{
    public function __construct(
        private readonly WithdrawNotificationStrategyFactory $strategyFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function listen(): array
    {
        return [
            WithdrawCompleted::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof WithdrawCompleted) {
            return;
        }

        $withdraw = $event->withdraw();

        try {
            $strategy = $this->strategyFactory->create($withdraw->method());

            if ($strategy === null) {
                $this->logger->warning('No notification strategy for withdraw method', [
                    'withdraw_id' => $withdraw->id()->value(),
                    'method' => $withdraw->method()->value,
                ]);

                return;
            }

            $strategy->notify($withdraw, $event->methodData());
        } catch (Throwable $e) {
            $this->logger->error('Failed to send withdraw notification', [
                'withdraw_id' => $withdraw->id()->value(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
