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
use App\Domain\Exception\AccountNotFoundException;
use App\Domain\Exception\InvalidScheduleDateException;
use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\Port\EventDispatcherInterface;
use App\Domain\Port\TransactionManagerInterface;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Domain\Strategy\WithdrawMethodData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Hyperf\Contract\StdoutLoggerInterface;

class CreateWithdrawUseCase
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly WithdrawRepositoryInterface $withdrawRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WithdrawMethodFactory $factory,
        private readonly StdoutLoggerInterface $logger,
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
        $amount = Money::fromFloat($input->amount);
        $withdrawMethod = WithdrawMethod::from(strtolower(trim($input->method)));

        if ($input->schedule !== null) {
            $output = $this->handleScheduled($accountId, $withdrawMethod, $amount, $methodData, $input->schedule);
        } else {
            $output = $this->handleImmediate($accountId, $withdrawMethod, $amount, $methodData);
        }

        $this->logger->info('Withdraw completed', [
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

        /** @var array{0: AccountWithdraw, 1: Account} $result */
        $result = $this->transactionManager->execute(function () use ($accountId, $withdrawId, $method, $amount, $methodData) {
            $account = $this->accountRepository->findByIdWithLock($accountId);

            $account->withdraw($amount);

            $withdraw = AccountWithdraw::createImmediate($withdrawId, $accountId, $method, $amount);

            $this->accountRepository->save($account);
            $this->withdrawRepository->save($withdraw, $methodData);

            return [$withdraw, $account];
        });

        [$withdraw, $account] = $result;

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
        $scheduledFor = $this->parseScheduleDate($schedule);

        $account = $this->accountRepository->findById($accountId);
        if ($account === null) {
            throw new AccountNotFoundException($accountId->value());
        }

        $withdrawId = Uuid::generate();

        $withdraw = AccountWithdraw::createScheduled($withdrawId, $accountId, $method, $amount, $scheduledFor);

        $this->withdrawRepository->save($withdraw, $methodData);

        return $this->buildOutput($withdraw);
    }

    private function parseScheduleDate(string $schedule): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i', $schedule);

        if ($date === false) {
            throw InvalidScheduleDateException::invalidFormat($schedule);
        }

        if ($date <= new DateTimeImmutable()) {
            throw InvalidScheduleDateException::inThePast();
        }

        return $date;
    }

    private function buildOutput(AccountWithdraw $withdraw): CreateWithdrawOutput
    {
        return new CreateWithdrawOutput(
            id: $withdraw->id()->value(),
            accountId: $withdraw->accountId()->value(),
            method: $withdraw->method()->value,
            amount: (float) $withdraw->amount()->toDecimal(),
            scheduled: $withdraw->isScheduled(),
            scheduledFor: $withdraw->scheduledFor()?->format('Y-m-d H:i:s'),
            done: $withdraw->isDone(),
            createdAt: $withdraw->createdAt()->format(DateTimeImmutable::ATOM),
        );
    }
}
