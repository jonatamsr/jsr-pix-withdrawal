<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Persistence\Mapper;

use App\Domain\Entity\Account;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Mapper\AccountMapper;
use App\Infrastructure\Persistence\Model\AccountModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AccountMapperTest extends TestCase
{
    private AccountMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AccountMapper();
    }

    #[Test]
    public function toDomainHydratesAccountCorrectly(): void
    {
        $model = new AccountModel();
        $model->id = '550e8400-e29b-41d4-a716-446655440000';
        $model->name = 'John Doe';
        $model->balance = '1500.50';

        $account = $this->mapper->toDomain($model);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $account->id()->value());
        $this->assertSame('John Doe', $account->name());
        $this->assertSame('1500.50', $account->balance()->toDecimal());
    }

    #[Test]
    public function toModelExtractsDataCorrectly(): void
    {
        $account = Account::create(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            'Jane Doe',
            Money::fromFloat(250.75),
        );

        $data = $this->mapper->toModel($account);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $data['id']);
        $this->assertSame('Jane Doe', $data['name']);
        $this->assertSame('250.75', $data['balance']);
    }

    #[Test]
    public function roundTripPreservesData(): void
    {
        $original = Account::create(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            'Test User',
            Money::fromFloat(999.99),
        );

        $data = $this->mapper->toModel($original);

        $model = new AccountModel();
        $model->id = $data['id'];
        $model->name = $data['name'];
        $model->balance = $data['balance'];

        $restored = $this->mapper->toDomain($model);

        $this->assertSame($original->id()->value(), $restored->id()->value());
        $this->assertSame($original->name(), $restored->name());
        $this->assertSame($original->balance()->toDecimal(), $restored->balance()->toDecimal());
    }
}
