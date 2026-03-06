<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\UseCase;

use App\Application\DTO\CreateWithdrawInput;
use App\Application\Factory\WithdrawMethodFactory;
use App\Application\UseCase\CreateWithdrawUseCase;
use App\Domain\Entity\Account;
use App\Domain\Entity\AccountWithdraw;
use App\Domain\Event\WithdrawCompleted;
use App\Domain\Exception\AccountNotFoundException;
use App\Domain\Exception\InsufficientBalanceException;
use App\Domain\Exception\InvalidScheduleDateException;
use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\Port\EventDispatcherInterface;
use App\Domain\Port\TransactionManagerInterface;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\Strategy\WithdrawMethodData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use HyperfTest\Support\MocksLogger;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class CreateWithdrawUseCaseTest extends TestCase
{
    use MocksLogger;
    use UsesMockery;

    private AccountRepositoryInterface|MockInterface $accountRepo;

    private MockInterface|WithdrawRepositoryInterface $withdrawRepo;

    private EventDispatcherInterface|MockInterface $eventDispatcher;

    private MockInterface|TransactionManagerInterface $transactionManager;

    private CreateWithdrawUseCase $useCase;

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

        $this->useCase = new CreateWithdrawUseCase(
            $this->accountRepo,
            $this->withdrawRepo,
            $this->eventDispatcher,
            new WithdrawMethodFactory(),
            $this->silentLogger(),
            $this->transactionManager,
        );
    }

    // -- Immediate withdrawal --

    #[Test]
    public function immediateWithdrawalDeductsBalanceAndMarksDone(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(1000.00));

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->once()
            ->andReturn($account);

        $this->accountRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Account $account) => $account->balance()->toDecimal() === '849.25'));

        $this->withdrawRepo->shouldReceive('save')
            ->once()
            ->with(
                Mockery::type(AccountWithdraw::class),
                Mockery::type(WithdrawMethodData::class),
            );

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function (WithdrawCompleted $event): bool {
                $this->assertNotNull($event->methodData());
                $this->assertInstanceOf(PixWithdrawData::class, $event->methodData());

                return true;
            }));

        $output = $this->useCase->execute(new CreateWithdrawInput(
            accountId: $accountId->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'someone@email.com'],
            amount: 150.75,
        ));

        $this->assertTrue($output->done);
        $this->assertFalse($output->scheduled);
        $this->assertNull($output->scheduledFor);
        $this->assertSame(150.75, $output->amount);
        $this->assertSame($accountId->value(), $output->accountId);
        $this->assertSame('pix', $output->method);
    }

    #[Test]
    public function immediateWithdrawalSavesPixData(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(500.00));

        $this->accountRepo->shouldReceive('findByIdWithLock')->andReturn($account);
        $this->accountRepo->shouldReceive('save');
        $this->eventDispatcher->shouldReceive('dispatch');

        $savedMethodData = null;
        $this->withdrawRepo->shouldReceive('save')
            ->once()
            ->with(
                Mockery::type(AccountWithdraw::class),
                Mockery::on(function (WithdrawMethodData $data) use (&$savedMethodData) {
                    $savedMethodData = $data;
                    return true;
                }),
            );

        $this->useCase->execute(new CreateWithdrawInput(
            accountId: $accountId->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'someone@email.com'],
            amount: 100.00,
        ));

        $this->assertInstanceOf(PixWithdrawData::class, $savedMethodData);
        assert($savedMethodData instanceof PixWithdrawData);
        $this->assertSame('someone@email.com', $savedMethodData->getPixKey()->key());
    }

    // -- Scheduled withdrawal --

    #[Test]
    public function scheduledWithdrawalDoesNotDeductBalance(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(1000.00));

        $this->accountRepo->shouldReceive('findById')
            ->once()
            ->andReturn($account);

        $this->accountRepo->shouldNotReceive('save');

        $this->withdrawRepo->shouldReceive('save')
            ->once()
            ->with(
                Mockery::type(AccountWithdraw::class),
                Mockery::type(WithdrawMethodData::class),
            );

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $output = $this->useCase->execute(new CreateWithdrawInput(
            accountId: $accountId->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'fulano@email.com'],
            amount: 150.75,
            schedule: '2027-06-15 10:00',
        ));

        $this->assertFalse($output->done);
        $this->assertTrue($output->scheduled);
        $this->assertSame('2027-06-15 10:00:00', $output->scheduledFor);
        $this->assertSame(150.75, $output->amount);
        $this->assertSame('1000.00', $account->balance()->toDecimal());
    }

    // -- Schedule date validation --

    #[Test]
    public function pastScheduleDateThrowsException(): void
    {
        $this->expectException(InvalidScheduleDateException::class);
        $this->expectExceptionMessage('The schedule date must be in the future');

        $this->useCase->execute(new CreateWithdrawInput(
            accountId: Uuid::generate()->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'fulano@email.com'],
            amount: 100.00,
            schedule: '2020-01-01 10:00',
        ));
    }

    #[Test]
    public function invalidScheduleFormatThrowsException(): void
    {
        $this->expectException(InvalidScheduleDateException::class);
        $this->expectExceptionMessage('Invalid schedule date format: not-a-date');

        $this->useCase->execute(new CreateWithdrawInput(
            accountId: Uuid::generate()->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'fulano@email.com'],
            amount: 100.00,
            schedule: 'not-a-date',
        ));
    }

    // -- Insufficient balance --

    #[Test]
    public function insufficientBalanceThrowsException(): void
    {
        $accountId = Uuid::generate();
        $account = Account::create($accountId, 'Test User', Money::fromFloat(50.00));

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->once()
            ->andReturn($account);

        $this->expectException(InsufficientBalanceException::class);

        $this->useCase->execute(new CreateWithdrawInput(
            accountId: $accountId->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'fulano@email.com'],
            amount: 150.75,
        ));
    }

    // -- Account not found --

    #[Test]
    public function accountNotFoundThrowsOnImmediate(): void
    {
        $accountId = Uuid::generate();

        $this->accountRepo->shouldReceive('findByIdWithLock')
            ->once()
            ->andThrow(new AccountNotFoundException($accountId->value()));

        $this->expectException(AccountNotFoundException::class);

        $this->useCase->execute(new CreateWithdrawInput(
            accountId: $accountId->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'fulano@email.com'],
            amount: 100.00,
        ));
    }

    #[Test]
    public function accountNotFoundThrowsOnScheduled(): void
    {
        $accountId = Uuid::generate();

        $this->accountRepo->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $this->expectException(AccountNotFoundException::class);

        $this->useCase->execute(new CreateWithdrawInput(
            accountId: $accountId->value(),
            method: 'PIX',
            methodData: ['type' => 'email', 'key' => 'fulano@email.com'],
            amount: 100.00,
            schedule: '2027-06-15 10:00',
        ));
    }
}
