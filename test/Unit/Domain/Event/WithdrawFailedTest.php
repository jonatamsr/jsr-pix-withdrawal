<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Event;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Event\DomainEvent;
use App\Domain\Event\WithdrawFailed;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class WithdrawFailedTest extends TestCase
{
    #[Test]
    public function implementsDomainEventInterface(): void
    {
        $event = $this->createEvent();

        $this->assertInstanceOf(DomainEvent::class, $event);
    }

    #[Test]
    public function carriesWithdrawAndReason(): void
    {
        $withdraw = $this->createWithdraw();
        $reason = 'Insufficient balance';

        $event = new WithdrawFailed($withdraw, $reason);

        $this->assertSame($withdraw, $event->withdraw());
        $this->assertSame('Insufficient balance', $event->reason());
    }

    #[Test]
    public function occurredAtIsSet(): void
    {
        $event = $this->createEvent();

        $this->assertNotNull($event->occurredAt());
    }

    private function createEvent(): WithdrawFailed
    {
        return new WithdrawFailed($this->createWithdraw(), 'Insufficient balance');
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
}
