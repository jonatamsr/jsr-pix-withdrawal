<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Persistence\Mapper;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Mapper\WithdrawMapper;
use App\Infrastructure\Persistence\Model\AccountWithdrawModel;
use App\Infrastructure\Persistence\Model\AccountWithdrawPixModel;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    // -- toModel --

    #[Test]
    public function toModelExtractsImmediateWithdrawDataCorrectly(): void
    {
        $withdraw = AccountWithdraw::createImmediate(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'),
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            WithdrawMethod::PIX,
            Money::fromFloat(150.75),
        );

        $data = $this->mapper->toModel($withdraw);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $data['id']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $data['account_id']);
        $this->assertSame('pix', $data['method']);
        $this->assertSame('150.75', $data['amount']);
        $this->assertFalse($data['scheduled']);
        $this->assertNull($data['scheduled_for']);
        $this->assertTrue($data['done']);
        $this->assertFalse($data['error']);
        $this->assertNull($data['error_reason']);
    }

    #[Test]
    public function toModelExtractsScheduledWithdrawDataCorrectly(): void
    {
        $scheduledFor = new DateTimeImmutable('2026-06-15 10:00:00');

        $withdraw = AccountWithdraw::createScheduled(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440002'),
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            WithdrawMethod::PIX,
            Money::fromFloat(500.00),
            $scheduledFor,
        );

        $data = $this->mapper->toModel($withdraw);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440002', $data['id']);
        $this->assertTrue($data['scheduled']);
        $this->assertSame('2026-06-15 10:00:00', $data['scheduled_for']);
        $this->assertFalse($data['done']);
        $this->assertFalse($data['error']);
    }

    // -- pixToModel --

    #[Test]
    public function pixToModelExtractsDataCorrectly(): void
    {
        $pix = AccountWithdrawPix::create(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'),
            PixKey::create('email', 'fulano@email.com'),
        );

        $data = $this->mapper->pixToModel($pix);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440001', $data['account_withdraw_id']);
        $this->assertSame('email', $data['type']);
        $this->assertSame('fulano@email.com', $data['key']);
    }

    // -- toDateTimeImmutable (private) --

    #[Test]
    public function toDateTimeImmutableReturnsValueWhenAlreadyDateTimeImmutable(): void
    {
        $method = new ReflectionMethod(WithdrawMapper::class, 'toDateTimeImmutable');
        $expected = new DateTimeImmutable('2026-03-05 10:30:00');

        $result = $method->invoke($this->mapper, $expected);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function toDateTimeImmutableParsesStringValue(): void
    {
        $method = new ReflectionMethod(WithdrawMapper::class, 'toDateTimeImmutable');

        $result = $method->invoke($this->mapper, '2026-06-15 12:00:00');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2026-06-15 12:00:00', $result->format('Y-m-d H:i:s'));
    }
}
