<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Persistence\Mapper;

use App\Domain\Enum\WithdrawMethod;
use App\Infrastructure\Persistence\Mapper\WithdrawMapper;
use App\Infrastructure\Persistence\Model\AccountWithdrawModel;
use App\Infrastructure\Persistence\Model\AccountWithdrawPixModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class WithdrawMapperTest extends TestCase
{
    private WithdrawMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new WithdrawMapper();
    }

    // -- toDomain --

    #[Test]
    public function toDomainHydratesImmediateWithdrawCorrectly(): void
    {
        $model = new AccountWithdrawModel();
        $model->id = '550e8400-e29b-41d4-a716-446655440001';
        $model->account_id = '550e8400-e29b-41d4-a716-446655440000';
        $model->method = 'pix';
        $model->amount = '150.75';
        $model->scheduled = false;
        $model->scheduled_for = null;
        $model->done = true;
        $model->error = false;
        $model->error_reason = null;
        $model->created_at = '2026-03-05 10:30:00';

        $withdraw = $this->mapper->toDomain($model);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $withdraw->id()->value());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $withdraw->accountId()->value());
        $this->assertSame(WithdrawMethod::PIX, $withdraw->method());
        $this->assertSame('150.75', $withdraw->amount()->toDecimal());
        $this->assertFalse($withdraw->isScheduled());
        $this->assertNull($withdraw->scheduledFor());
        $this->assertTrue($withdraw->isDone());
        $this->assertFalse($withdraw->hasError());
        $this->assertNull($withdraw->errorReason());
        $this->assertSame('2026-03-05 10:30:00', $withdraw->createdAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function toDomainHydratesScheduledWithdrawCorrectly(): void
    {
        $model = new AccountWithdrawModel();
        $model->id = '550e8400-e29b-41d4-a716-446655440002';
        $model->account_id = '550e8400-e29b-41d4-a716-446655440000';
        $model->method = 'pix';
        $model->amount = '500.00';
        $model->scheduled = true;
        $model->scheduled_for = '2026-06-15 10:00:00';
        $model->done = false;
        $model->error = false;
        $model->error_reason = null;
        $model->created_at = '2026-03-05 10:30:00';

        $withdraw = $this->mapper->toDomain($model);

        $this->assertTrue($withdraw->isScheduled());
        $this->assertNotNull($withdraw->scheduledFor());
        $this->assertSame('2026-06-15 10:00:00', $withdraw->scheduledFor()->format('Y-m-d H:i:s'));
        $this->assertFalse($withdraw->isDone());
    }

    #[Test]
    public function toDomainHydratesFailedWithdrawCorrectly(): void
    {
        $model = new AccountWithdrawModel();
        $model->id = '550e8400-e29b-41d4-a716-446655440003';
        $model->account_id = '550e8400-e29b-41d4-a716-446655440000';
        $model->method = 'pix';
        $model->amount = '200.00';
        $model->scheduled = true;
        $model->scheduled_for = '2026-03-01 10:00:00';
        $model->done = false;
        $model->error = true;
        $model->error_reason = 'Insufficient balance';
        $model->created_at = '2026-02-28 10:00:00';

        $withdraw = $this->mapper->toDomain($model);

        $this->assertTrue($withdraw->hasError());
        $this->assertSame('Insufficient balance', $withdraw->errorReason());
        $this->assertFalse($withdraw->isDone());
    }

    // -- pixToDomain --

    #[Test]
    public function pixToDomainHydratesCorrectly(): void
    {
        $model = new AccountWithdrawPixModel();
        $model->account_withdraw_id = '550e8400-e29b-41d4-a716-446655440001';
        $model->type = 'email';
        $model->key = 'fulano@email.com';

        $pix = $this->mapper->pixToDomain($model);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $pix->accountWithdrawId()->value());
        $this->assertSame('email', $pix->pixKey()->type()->value);
        $this->assertSame('fulano@email.com', $pix->pixKey()->key());
    }
}
