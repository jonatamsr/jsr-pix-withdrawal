<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mapper;

use App\Domain\Entity\Account;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Model\AccountModel;

class AccountMapper
{
    public function toDomain(AccountModel $model): Account
    {
        return Account::create(
            Uuid::fromString($model->id),
            $model->name,
            Money::fromString($model->balance),
        );
    }

    public function toModel(Account $entity): array
    {
        return [
            'id' => $entity->id()->value(),
            'name' => $entity->name(),
            'balance' => $entity->balance()->toDecimal(),
        ];
    }
}
