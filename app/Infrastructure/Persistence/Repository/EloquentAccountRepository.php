<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\Account;
use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Mapper\AccountMapper;
use App\Infrastructure\Persistence\Model\AccountModel;

class EloquentAccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private readonly AccountModel $model,
        private readonly AccountMapper $mapper,
    ) {
    }

    public function findById(Uuid $id): ?Account
    {
        /** @var null|AccountModel $found */
        $found = $this->model->newQuery()->find($id->value());

        if ($found === null) {
            return null;
        }

        return $this->mapper->toDomain($found);
    }

    public function findByIdWithLock(Uuid $id): ?Account
    {
        /** @var null|AccountModel $found */
        $found = $this->model->newQuery()
            ->where('id', $id->value())
            ->lockForUpdate()
            ->first();

        if ($found === null) {
            return null;
        }

        return $this->mapper->toDomain($found);
    }

    public function save(Account $account): void
    {
        $data = $this->mapper->toModel($account);

        $this->model->newQuery()->updateOrCreate(
            ['id' => $data['id']],
            $data,
        );
    }
}
