<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\UseCase;

use App\Application\UseCase\ProcessScheduledWithdrawsUseCase;
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
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PendingWithdrawal;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use HyperfTest\Support\MocksLogger;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ProcessScheduledWithdrawsUseCaseTest extends TestCase
{
    use MocksLogger;
    use UsesMockery;

    private AccountRepositoryInterface|MockInterface $accountRepo;

    private MockInterface|WithdrawRepositoryInterface $withdrawRepo;

    private EventDispatcherInterface|MockInterface $eventDispatcher;

    private MockInterface|TransactionManagerInterface $transactionManager;

    private ProcessScheduledWithdrawsUseCase $useCase;

    protected function setUp(): void
    {
        $this->accountRepo = Mockery::mock(AccountRepositoryInterface::class);
        $this->withdrawRepo = Mockery::mock(WithdrawRepositoryInterface::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $this->transactionManager = Mockery::mock(TransactionManagerInterface::class);

        $this->transactionManager->shouldReceive('execute')
            ->andReturnUsing(function (callable $callback) {
                return $callback();
            })
            ->byDefault();

        $this->useCase = new ProcessScheduledWithdrawsUseCase(
            $this->accountRepo,
            $this->withdrawRepo,
            $this->eventDispatcher,
            $this->silentLogger(),
            $this->transactionManager,
        );
    }

    // -- No pending withdrawals --

    #[Test]
    public function noPendingWithdrawalsDoesNothing(): void
    {
        $this->withdrawRepo->shouldReceive('findPendingScheduled')
            ->once()
            ->andReturn([]);

        $this->accountRepo->shouldNotReceive('findByIdWithLock');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->useCase->execute();
    }

    // -- Successful processing --

    #[Test]
    public function processesWithdrawalSuccessfullyDeductingBalance(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(500.00));
        $methodData = new PixWithdrawData(PixKey::create('email', 'user@example.com'));

        $withdraw = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $accountId,
            WithdrawMethod::PIX,
            Money::fromFloat(150.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $this->withdrawRepo->shouldReceive('findPendingScheduled')
            ->once()
            ->andReturn([new PendingWithdrawal($withdraw, $methodData)]);

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->once()
            ->with(Mockery::on(fn (Uuid $id) => $id->value() === $accountId->value()))
            ->andReturn($account);

        $this->accountRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Account $a) => $a->balance()->toDecimal() === '350.00'));

        $this->withdrawRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (AccountWithdraw $w) => $w->isDone() && ! $w->hasError()));

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(WithdrawCompleted::class));

        $this->useCase->execute();
    }

    // -- Insufficient balance --

    #[Test]
    public function insufficientBalanceMarksErrorWithoutThrowing(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(50.00));

        $withdraw = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $accountId,
            WithdrawMethod::PIX,
            Money::fromFloat(150.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $this->withdrawRepo->shouldReceive('findPendingScheduled')
            ->once()
            ->andReturn([new PendingWithdrawal($withdraw)]);

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->once()
            ->andReturn($account);

        $this->withdrawRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (AccountWithdraw $w) => $w->hasError()
                && $w->errorReason() === 'Insufficient balance'
                && ! $w->isDone()));

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn ($event) => $event instanceof WithdrawFailed
                && $event->reason() === 'Insufficient balance'));

        $this->useCase->execute();

        $this->assertSame('50.00', $account->balance()->toDecimal());
    }

    // -- Batch isolation: one failure does not affect others --

    #[Test]
    public function oneFailureDoesNotAffectOtherWithdrawals(): void
    {
        $account1Id = Uuid::generate();
        $account1 = Account::create($account1Id, 'User One', Money::fromFloat(10.00));

        $account2Id = Uuid::generate();
        $account2 = Account::create($account2Id, 'User Two', Money::fromFloat(500.00));

        $withdrawFail = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $account1Id,
            WithdrawMethod::PIX,
            Money::fromFloat(100.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $withdrawSuccess = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $account2Id,
            WithdrawMethod::PIX,
            Money::fromFloat(200.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $this->withdrawRepo->shouldReceive('findPendingScheduled')
            ->once()
            ->andReturn([new PendingWithdrawal($withdrawFail), new PendingWithdrawal($withdrawSuccess)]);

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->with(Mockery::on(fn (Uuid $id) => $id->value() === $account1Id->value()))
            ->andReturn($account1);

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->with(Mockery::on(fn (Uuid $id) => $id->value() === $account2Id->value()))
            ->andReturn($account2);

        $this->accountRepo->shouldReceive('save')->once();

        $this->withdrawRepo->shouldReceive('save')->twice();

        $dispatchedEvents = [];
        $this->eventDispatcher->shouldReceive('dispatch')
            ->twice()
            ->with(Mockery::on(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return true;
            }));

        $this->useCase->execute();

        $this->assertInstanceOf(WithdrawFailed::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(WithdrawCompleted::class, $dispatchedEvents[1]);

        $this->assertTrue($withdrawFail->hasError());
        $this->assertFalse($withdrawFail->isDone());

        $this->assertTrue($withdrawSuccess->isDone());
        $this->assertFalse($withdrawSuccess->hasError());

        $this->assertSame('300.00', $account2->balance()->toDecimal());
    }

    // -- Multiple successful withdrawals --

    #[Test]
    public function processesMultipleSuccessfulWithdrawals(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(1000.00));

        $withdraw1 = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $accountId,
            WithdrawMethod::PIX,
            Money::fromFloat(100.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $withdraw2 = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $accountId,
            WithdrawMethod::PIX,
            Money::fromFloat(200.00),
            new DateTimeImmutable('2026-03-01 11:00'),
        );

        $this->withdrawRepo->shouldReceive('findPendingScheduled')
            ->once()
            ->andReturn([new PendingWithdrawal($withdraw1), new PendingWithdrawal($withdraw2)]);

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->twice()
            ->andReturn($account);

        $this->accountRepo->shouldReceive('save')->twice();
        $this->withdrawRepo->shouldReceive('save')->twice();

        $this->eventDispatcher->shouldReceive('dispatch')
            ->twice()
            ->with(Mockery::type(WithdrawCompleted::class));

        $this->useCase->execute();

        $this->assertSame('700.00', $account->balance()->toDecimal());
    }

    // -- Unexpected error is caught and does not break batch --

    #[Test]
    public function unexpectedErrorMarksFailureAndDoesNotBreakBatch(): void
    {
        $account1Id = Uuid::generate();
        $account2Id = Uuid::generate();
        $account2 = Account::create($account2Id, 'User Two', Money::fromFloat(500.00));

        $withdraw1 = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $account1Id,
            WithdrawMethod::PIX,
            Money::fromFloat(100.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $withdraw2 = AccountWithdraw::createScheduled(
            Uuid::generate(),
            $account2Id,
            WithdrawMethod::PIX,
            Money::fromFloat(200.00),
            new DateTimeImmutable('2026-03-01 10:00'),
        );

        $this->withdrawRepo->shouldReceive('findPendingScheduled')
            ->once()
            ->andReturn([new PendingWithdrawal($withdraw1), new PendingWithdrawal($withdraw2)]);

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->with(Mockery::on(fn (Uuid $id) => $id->value() === $account1Id->value()))
            ->andThrow(new AccountNotFoundException($account1Id->value()));

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->with(Mockery::on(fn (Uuid $id) => $id->value() === $account2Id->value()))
            ->andReturn($account2);

        $this->accountRepo->shouldReceive('save')->once();
        $this->withdrawRepo->shouldReceive('save')->twice();

        $dispatchedEvents = [];
        $this->eventDispatcher->shouldReceive('dispatch')
            ->twice()
            ->with(Mockery::on(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return true;
            }));

        $this->useCase->execute();

        $this->assertInstanceOf(WithdrawFailed::class, $dispatchedEvents[0]);
        $this->assertStringContainsString('Unexpected error', $dispatchedEvents[0]->reason());
        $this->assertTrue($withdraw1->hasError());
        $this->assertStringContainsString('Unexpected error', $withdraw1->errorReason());

        $this->assertInstanceOf(WithdrawCompleted::class, $dispatchedEvents[1]);
        $this->assertTrue($withdraw2->isDone());
        $this->assertSame('300.00', $account2->balance()->toDecimal());
    }
}
