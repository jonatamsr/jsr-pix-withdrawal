<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Entity;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AccountWithdrawTest extends TestCase
{
    // -- Immediate creation --

    #[Test]
    public function createImmediateWithdraw(): void
    {
        $id = Uuid::generate();
        $accountId = Uuid::generate();
        $amount = Money::fromFloat(150.75);

        $withdraw = AccountWithdraw::createImmediate(
            id: $id,
            accountId: $accountId,
            method: WithdrawMethod::PIX,
            amount: $amount
        );

        $this->assertSame($id, $withdraw->id());
        $this->assertSame($accountId, $withdraw->accountId());
        $this->assertSame(WithdrawMethod::PIX, $withdraw->method());
        $this->assertSame('150.75', $withdraw->amount()->toDecimal());
        $this->assertFalse($withdraw->isScheduled());
        $this->assertNull($withdraw->scheduledFor());
        $this->assertTrue($withdraw->isDone());
        $this->assertFalse($withdraw->hasError());
        $this->assertNull($withdraw->errorReason());
    }

    // -- Scheduled creation --

    #[Test]
    public function createScheduledWithdraw(): void
    {
        $id = Uuid::generate();
        $accountId = Uuid::generate();
        $amount = Money::fromFloat(500.00);
        $scheduledFor = new DateTimeImmutable('+1 day');

        $withdraw = AccountWithdraw::createScheduled(
            id: $id,
            accountId: $accountId,
            method: WithdrawMethod::PIX,
            amount: $amount,
            scheduledFor: $scheduledFor
        );

        $this->assertSame($id, $withdraw->id());
        $this->assertSame($accountId, $withdraw->accountId());
        $this->assertSame(WithdrawMethod::PIX, $withdraw->method());
        $this->assertSame('500.00', $withdraw->amount()->toDecimal());
        $this->assertTrue($withdraw->isScheduled());
        $this->assertSame($scheduledFor, $withdraw->scheduledFor());
        $this->assertFalse($withdraw->isDone());
        $this->assertFalse($withdraw->hasError());
        $this->assertNull($withdraw->errorReason());
    }

    // -- Mark as done --

    #[Test]
    public function markAsDoneSetsCorrectState(): void
    {
        $withdraw = $this->createPendingScheduledWithdraw();

        $withdraw->markAsDone();

        $this->assertTrue($withdraw->isDone());
        $this->assertFalse($withdraw->hasError());
        $this->assertNull($withdraw->errorReason());
    }

    #[Test]
    public function markAsDoneClearsPreviousError(): void
    {
        $withdraw = $this->createPendingScheduledWithdraw();

        $withdraw->markAsFailed('Insufficient balance');
        $withdraw->markAsDone();

        $this->assertTrue($withdraw->isDone());
        $this->assertFalse($withdraw->hasError());
        $this->assertNull($withdraw->errorReason());
    }

    // -- Mark as failed --

    #[Test]
    public function markAsFailedSetsErrorState(): void
    {
        $withdraw = $this->createPendingScheduledWithdraw();

        $withdraw->markAsFailed('Insufficient balance');

        $this->assertFalse($withdraw->isDone());
        $this->assertTrue($withdraw->hasError());
        $this->assertSame('Insufficient balance', $withdraw->errorReason());
    }

    // -- Reconstitute --

    #[Test]
    public function reconstituteRestoresFullState(): void
    {
        $id = Uuid::generate();
        $accountId = Uuid::generate();
        $amount = Money::fromFloat(300.00);
        $scheduledFor = new DateTimeImmutable('2026-03-01 10:00:00');
        $createdAt = new DateTimeImmutable('2026-02-28 09:00:00');

        $withdraw = AccountWithdraw::reconstitute(
            id: $id,
            accountId: $accountId,
            method: WithdrawMethod::PIX,
            amount: $amount,
            scheduled: true,
            scheduledFor: $scheduledFor,
            done: false,
            error: true,
            errorReason: 'Insufficient balance',
            createdAt: $createdAt
        );

        $this->assertSame($id, $withdraw->id());
        $this->assertSame($accountId, $withdraw->accountId());
        $this->assertSame(WithdrawMethod::PIX, $withdraw->method());
        $this->assertSame('300.00', $withdraw->amount()->toDecimal());
        $this->assertTrue($withdraw->isScheduled());
        $this->assertSame($scheduledFor, $withdraw->scheduledFor());
        $this->assertFalse($withdraw->isDone());
        $this->assertTrue($withdraw->hasError());
        $this->assertSame('Insufficient balance', $withdraw->errorReason());
        $this->assertSame($createdAt, $withdraw->createdAt());
    }

    private function createPendingScheduledWithdraw(): AccountWithdraw
    {
        return AccountWithdraw::createScheduled(
            id: Uuid::generate(),
            accountId: Uuid::generate(),
            method: WithdrawMethod::PIX,
            amount: Money::fromFloat(100.00),
            scheduledFor: new DateTimeImmutable('+1 day')
        );
    }
}
