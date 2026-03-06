<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PendingWithdrawal;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class PendingWithdrawalTest extends TestCase
{
    #[Test]
    public function exposesWithdrawAndMethodData(): void
    {
        $withdraw = AccountWithdraw::createImmediate(
            Uuid::generate(),
            Uuid::generate(),
            WithdrawMethod::PIX,
            Money::fromFloat(100.00),
        );

        $methodData = new PixWithdrawData(PixKey::create('email', 'user@example.com'));

        $pending = new PendingWithdrawal($withdraw, $methodData);

        $this->assertSame($withdraw, $pending->withdraw());
        $this->assertSame($methodData, $pending->methodData());
    }

    #[Test]
    public function methodDataDefaultsToNull(): void
    {
        $withdraw = AccountWithdraw::createImmediate(
            Uuid::generate(),
            Uuid::generate(),
            WithdrawMethod::PIX,
            Money::fromFloat(100.00),
        );

        $pending = new PendingWithdrawal($withdraw);

        $this->assertSame($withdraw, $pending->withdraw());
        $this->assertNull($pending->methodData());
    }
}
