<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\CreateWithdrawInput;
use App\Application\DTO\CreateWithdrawOutput;
use App\Application\Factory\WithdrawMethodFactory;
use App\Domain\Entity\Account;
use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Event\WithdrawCompleted;
use App\Domain\Event\WithdrawFailed;
use App\Domain\Exception\AccountNotFoundException;
use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\Port\EventDispatcherInterface;
use App\Domain\Port\TransactionManagerInterface;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Domain\Strategy\WithdrawMethodData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\ScheduleDate;
use App\Domain\ValueObject\Uuid;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateWithdrawUseCase
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly WithdrawRepositoryInterface $withdrawRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WithdrawMethodFactory $factory,
        private readonly LoggerInterface $logger,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function execute(CreateWithdrawInput $input): CreateWithdrawOutput
    {
        $this->logger->info('Withdraw requested', [
            'account_id' => $input->accountId,
            'method' => $input->method,
            'amount' => $input->amount,
            'scheduled' => $input->schedule !== null,
        ]);

        $strategy = $this->factory->create($input->method);
        $methodData = $strategy->validateAndBuild($input->methodData);

        $accountId = Uuid::fromString($input->accountId);
        $withdrawMethod = WithdrawMethod::from(strtolower(trim($input->method)));
        $amount = Money::fromFloat($input->amount);

        if ($input->schedule !== null) {
            $output = $this->handleScheduled($accountId, $withdrawMethod, $amount, $methodData, $input->schedule);
        } else {
            $output = $this->handleImmediate($accountId, $withdrawMethod, $amount, $methodData);
        }

        $this->logger->info($output->scheduled ? 'Withdraw scheduled' : 'Withdraw completed', [
            'withdraw_id' => $output->id,
            'account_id' => $output->accountId,
            'done' => $output->done,
            'scheduled' => $output->scheduled,
        ]);

        return $output;
    }

    private function handleImmediate(
        Uuid $accountId,
        WithdrawMethod $method,
        Money $amount,
        WithdrawMethodData $methodData,
    ): CreateWithdrawOutput {
        $withdrawId = Uuid::generate();
        $withdraw = AccountWithdraw::createImmediate($withdrawId, $accountId, $method, $amount);

        try {
            /** @var Account $account */
            $account = $this->transactionManager->execute(function () use ($withdraw, $accountId, $amount, $methodData) {
                $account = $this->accountRepository->findByIdWithLock($accountId);
                if ($account === null) {
                    throw new AccountNotFoundException($accountId->value());
                }

                $account->withdraw($amount);

                $this->accountRepository->save($account);
                $this->withdrawRepository->save($withdraw, $methodData);

                return $account;
            });
        } catch (Throwable $e) {
            $this->eventDispatcher->dispatch(new WithdrawFailed($withdraw, $e->getMessage()));

            throw $e;
        }

        $this->eventDispatcher->dispatch(new WithdrawCompleted($withdraw, $account, $methodData));

        return $this->buildOutput($withdraw);
    }

    private function handleScheduled(
        Uuid $accountId,
        WithdrawMethod $method,
        Money $amount,
        WithdrawMethodData $methodData,
        string $schedule,
    ): CreateWithdrawOutput {
        try {
            $scheduledFor = ScheduleDate::fromString($schedule);

            $account = $this->accountRepository->findById($accountId);
            if ($account === null) {
                throw new AccountNotFoundException($accountId->value());
            }

            $withdrawId = Uuid::generate();

            $withdraw = AccountWithdraw::createScheduled($withdrawId, $accountId, $method, $amount, $scheduledFor->toDateTimeImmutable());

            $this->withdrawRepository->save($withdraw, $methodData);

            return $this->buildOutput($withdraw);
        } catch (Throwable $e) {
            $this->logger->warning('Withdraw schedule attempt failed', [
                'account_id' => $accountId->value(),
                'method' => $method->value,
                'amount' => $amount->toDecimal(),
                'schedule' => $schedule,
                'reason' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    private function buildOutput(AccountWithdraw $withdraw): CreateWithdrawOutput
    {
        return new CreateWithdrawOutput(
            id: $withdraw->id()->value(),
            accountId: $withdraw->accountId()->value(),
            method: $withdraw->method()->value,
            amount: (float) $withdraw->amount()->toDecimal(),
            scheduled: $withdraw->isScheduled(),
            scheduledFor: $withdraw->scheduledFor() !== null
                ? ScheduleDate::fromDateTimeImmutable($withdraw->scheduledFor())->toClientString()
                : null,
            done: $withdraw->isDone(),
        );
    }
}
