<?php

declare(strict_types=1);

namespace App\Crontab;

use App\Application\UseCase\ProcessScheduledWithdrawsUseCase;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Throwable;

#[Crontab(
    name: 'process-scheduled-withdraws',
    rule: '* * * * *',
    memo: 'Process scheduled withdrawals every minute',
    onOneServer: true,
)]
class ProcessScheduledWithdrawsCrontab
{
    public function __construct(
        private readonly ProcessScheduledWithdrawsUseCase $useCase,
        private readonly StdoutLoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        try {
            $this->logger->info('Crontab [process-scheduled-withdraws] started.');

            $this->useCase->execute();

            $this->logger->info('Crontab [process-scheduled-withdraws] finished.');
        } catch (Throwable $e) {
            $this->logger->error('Crontab [process-scheduled-withdraws] failed: ' . $e->getMessage(), [
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
