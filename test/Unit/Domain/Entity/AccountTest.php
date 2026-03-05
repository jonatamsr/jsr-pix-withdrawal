<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Entity;

use App\Domain\Entity\Account;
use App\Domain\Exception\InsufficientBalanceException;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AccountTest extends TestCase
{
    // -- Construction --

    #[Test]
    public function createAccountWithValidData(): void
    {
        $id = Uuid::generate();
        $balance = Money::fromFloat(1000.00);

        $account = Account::create($id, 'John Doe', $balance);

        $this->assertSame($id, $account->id());
        $this->assertSame('John Doe', $account->name());
        $this->assertSame('1000.00', $account->balance()->toDecimal());
    }

    #[Test]
    public function createAccountWithZeroBalance(): void
    {
        $account = Account::create(Uuid::generate(), 'Jane Doe', Money::zero());

        $this->assertSame('0.00', $account->balance()->toDecimal());
    }

    // -- Withdraw --

    #[Test]
    public function withdrawDeductsBalance(): void
    {
        $account = Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(500.00));

        $account->withdraw(Money::fromFloat(150.75));

        $this->assertSame('349.25', $account->balance()->toDecimal());
    }

    #[Test]
    public function withdrawExactBalance(): void
    {
        $account = Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(200.00));

        $account->withdraw(Money::fromFloat(200.00));

        $this->assertSame('0.00', $account->balance()->toDecimal());
    }

    #[Test]
    public function withdrawMultipleTimes(): void
    {
        $account = Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(1000.00));

        $account->withdraw(Money::fromFloat(300.00));
        $account->withdraw(Money::fromFloat(200.00));

        $this->assertSame('500.00', $account->balance()->toDecimal());
    }

    #[Test]
    public function withdrawThrowsOnInsufficientBalance(): void
    {
        $account = Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(100.00));

        $this->expectException(InsufficientBalanceException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $account->withdraw(Money::fromFloat(150.00));
    }

    #[Test]
    public function withdrawDoesNotDeductOnInsufficientBalance(): void
    {
        $account = Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(100.00));

        try {
            $account->withdraw(Money::fromFloat(150.00));
        } catch (InsufficientBalanceException $e) {
            $this->assertSame('Insufficient balance to complete this withdrawal', $e->getMessage());
        }

        $this->assertSame('100.00', $account->balance()->toDecimal());
    }

    #[Test]
    public function withdrawThrowsOnZeroAmount(): void
    {
        $account = Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(100.00));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Withdraw amount must be greater than zero');

        $account->withdraw(Money::zero());
    }
}
