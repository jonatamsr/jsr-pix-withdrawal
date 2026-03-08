<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Entity\Account;
use App\Domain\ValueObject\Uuid;

interface AccountRepositoryInterface
{
    public function findById(Uuid $id): ?Account;

    public function findByIdWithLock(Uuid $id): ?Account;

    public function save(Account $account): void;
}
