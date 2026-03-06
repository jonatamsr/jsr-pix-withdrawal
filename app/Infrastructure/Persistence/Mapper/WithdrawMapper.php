<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mapper;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Model\AccountWithdrawModel;
use App\Infrastructure\Persistence\Model\AccountWithdrawPixModel;
use Carbon\Carbon;
use DateTimeImmutable;

class WithdrawMapper
{
    public function toDomain(AccountWithdrawModel $model): AccountWithdraw
    {
        return AccountWithdraw::reconstitute(
            id: Uuid::fromString($model->id),
            accountId: Uuid::fromString($model->account_id),
            method: WithdrawMethod::from($model->method),
            amount: Money::fromString($model->amount),
            scheduled: $model->scheduled,
            scheduledFor: $this->toDateTimeImmutable($model->scheduled_for),
            done: $model->done,
            error: $model->error,
            errorReason: $model->error_reason,
            createdAt: $this->toDateTimeImmutable($model->created_at) ?? new DateTimeImmutable(),
        );
    }

    public function pixToDomain(AccountWithdrawPixModel $model): AccountWithdrawPix
    {
        return AccountWithdrawPix::create(
            Uuid::fromString($model->account_withdraw_id),
            PixKey::create($model->type, $model->key),
        );
    }

    private function toDateTimeImmutable(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeImmutable();
        }

        return new DateTimeImmutable((string) $value);
    }
}
