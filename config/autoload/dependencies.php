<?php

declare(strict_types=1);

use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Infrastructure\Persistence\Repository\EloquentAccountRepository;
use App\Infrastructure\Persistence\Repository\EloquentWithdrawRepository;

return [
    AccountRepositoryInterface::class => EloquentAccountRepository::class,
    WithdrawRepositoryInterface::class => EloquentWithdrawRepository::class,
];
