<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Event\WithdrawCompleted;
use App\Domain\Event\WithdrawFailed;
use App\Domain\Exception\AccountNotFoundException;
use App\Domain\Exception\InsufficientBalanceException;
use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\Port\EventDispatcherInterface;
use App\Domain\Port\TransactionManagerInterface;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Domain\Strategy\WithdrawMethodData;
use Psr\Log\LoggerInterface;
use Throwable;

class ProcessScheduledWithdrawsUseCase
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly WithdrawRepositoryInterface $withdrawRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function execute(): void
    {
        $pendingWithdrawals = $this->withdrawRepository->findPendingScheduled();

        $total = count($pendingWithdrawals);
        $processed = 0;
        $failed = 0;

        $this->logger->info('Scheduled withdrawals batch started', [
            'total_found' => $total,
        ]);

        foreach ($pendingWithdrawals as $pending) {
            $this->processWithdrawal($pending->withdraw(), $pending->methodData()) ? ++$processed : ++$failed;
        }

        $this->logger->info('Scheduled withdrawals batch completed', [
            'total_found' => $total,
            'processed' => $processed,
            'failed' => $failed,
        ]);
    }

    private function processWithdrawal(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData): bool
    {
        try {
            $account = $this->transactionManager->execute(function () use ($withdraw) {
                $account = $this->accountRepository->findByIdWithLock($withdraw->accountId());
                if ($account === null) {
                    throw new AccountNotFoundException($withdraw->accountId()->value());
                }

                $account->withdraw($withdraw->amount());

                $withdraw->markAsDone();

                $this->accountRepository->save($account);
                $this->withdrawRepository->save($withdraw);

                return $account;
            });

            $this->eventDispatcher->dispatch(new WithdrawCompleted($withdraw, $account, $methodData));

            $this->logger->info('Scheduled withdrawal processed successfully', [
                'withdraw_id' => $withdraw->id()->value(),
                'account_id' => $withdraw->accountId()->value(),
                'amount' => $withdraw->amount()->toDecimal(),
            ]);

            return true;
        } catch (InsufficientBalanceException) {
            $this->handleFailure($withdraw, 'Insufficient balance');

            return false;
        } catch (Throwable $e) {
            $this->handleFailure($withdraw, 'Unexpected error: ' . $e->getMessage());

            return false;
        }
    }

    private function handleFailure(AccountWithdraw $withdraw, string $reason): void
    {
        $this->transactionManager->execute(function () use ($withdraw, $reason) {
            $withdraw->markAsFailed($reason);
            $this->withdrawRepository->save($withdraw);
        });

        $this->eventDispatcher->dispatch(new WithdrawFailed($withdraw, $reason));
    }
}
