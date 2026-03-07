<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Account;
use App\Domain\Exception\AccountNotFoundException;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Mapper\AccountMapper;
use App\Infrastructure\Persistence\Model\AccountModel;
use App\Infrastructure\Persistence\Repository\EloquentAccountRepository;
use Hyperf\Database\Model\Builder;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EloquentAccountRepositoryTest extends TestCase
{
    use UsesMockery;

    private AccountMapper $mapper;

    private EloquentAccountRepository $repository;

    private AccountModel|MockInterface $model;

    private Builder|MockInterface $queryBuilder;

    protected function setUp(): void
    {
        $this->model = Mockery::mock(AccountModel::class);
        $this->queryBuilder = Mockery::mock(Builder::class);

        $this->mapper = new AccountMapper();
        $this->repository = new EloquentAccountRepository($this->model, $this->mapper);
    }

    // -- findById --

    #[Test]
    public function findByIdReturnsAccountWhenFound(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        $found = new AccountModel();
        $found->id = '550e8400-e29b-41d4-a716-446655440000';
        $found->name = 'John Doe';
        $found->balance = '1500.50';

        $this->model->shouldReceive('newQuery')->once()->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('find')
            ->with('550e8400-e29b-41d4-a716-446655440000')
            ->once()
            ->andReturn($found);

        $account = $this->repository->findById($uuid);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $account->id()->value());
        $this->assertSame('John Doe', $account->name());
        $this->assertSame('1500.50', $account->balance()->toDecimal());
    }

    #[Test]
    public function findByIdReturnsNullWhenNotFound(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440099');

        $this->model->shouldReceive('newQuery')->once()->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('find')
            ->with('550e8400-e29b-41d4-a716-446655440099')
            ->once()
            ->andReturn(null);

        $result = $this->repository->findById($uuid);

        $this->assertNull($result);
    }

    // -- findByIdWithLock --

    #[Test]
    public function findByIdWithLockReturnsAccountWhenFound(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        $found = new AccountModel();
        $found->id = '550e8400-e29b-41d4-a716-446655440000';
        $found->name = 'John Doe';
        $found->balance = '500.00';

        $this->model->shouldReceive('newQuery')->once()->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('where')
            ->with('id', '550e8400-e29b-41d4-a716-446655440000')
            ->once()
            ->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('first')
            ->once()
            ->andReturn($found);

        $account = $this->repository->findByIdWithLock($uuid);

        $this->assertInstanceOf(Account::class, $account);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $account->id()->value());
    }

    #[Test]
    public function findByIdWithLockThrowsWhenNotFound(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440099');

        $this->model->shouldReceive('newQuery')->once()->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('where')
            ->with('id', '550e8400-e29b-41d4-a716-446655440099')
            ->once()
            ->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('lockForUpdate')
            ->once()
            ->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('first')
            ->once()
            ->andReturn(null);

        $this->expectException(AccountNotFoundException::class);

        $this->repository->findByIdWithLock($uuid);
    }

    // -- save --

    #[Test]
    public function savePersistsAccountViaUpdateOrCreate(): void
    {
        $account = Account::create(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            'Jane Doe',
            Money::fromFloat(250.75),
        );

        $expectedData = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Jane Doe',
            'balance' => '250.75',
        ];

        $this->model->shouldReceive('newQuery')->once()->andReturn($this->queryBuilder);
        $this->queryBuilder->shouldReceive('updateOrCreate')
            ->with(
                ['id' => '550e8400-e29b-41d4-a716-446655440000'],
                $expectedData,
            )
            ->once();

        $this->repository->save($account);
    }
}
