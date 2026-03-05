<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Event;

use App\Domain\Entity\Account;
use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Event\DomainEvent;
use App\Domain\Event\WithdrawCompleted;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class WithdrawCompletedTest extends TestCase
{
    #[Test]
    public function implementsDomainEventInterface(): void
    {
        $event = $this->createEvent();

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    #[Test]
    public function carriesWithdrawData(): void
    {
        $withdraw = $this->createWithdraw();
        $account = $this->createAccount();

        $event = new WithdrawCompleted($withdraw, $account);

        $this->assertSame($withdraw, $event->withdraw());
        $this->assertSame($account, $event->account());
    }

    #[Test]
    public function occurredAtIsSet(): void
    {
        $event = $this->createEvent();

        $this->assertNotNull($event->occurredAt());
    }

    private function createEvent(): WithdrawCompleted
    {
        return new WithdrawCompleted($this->createWithdraw(), $this->createAccount());
    }

    private function createWithdraw(): AccountWithdraw
    {
        return AccountWithdraw::createImmediate(
            Uuid::generate(),
            Uuid::generate(),
            WithdrawMethod::PIX,
            Money::fromFloat(100.00)
        );
    }

    private function createAccount(): Account
    {
        return Account::create(Uuid::generate(), 'John Doe', Money::fromFloat(1000.00));
    }
}
