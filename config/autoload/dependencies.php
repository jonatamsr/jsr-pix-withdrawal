<?php

declare(strict_types=1);

use App\Domain\Port\AccountRepositoryInterface;
use App\Domain\Port\EventDispatcherInterface;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Infrastructure\Event\HyperfEventDispatcherAdapter;
use App\Infrastructure\Mail\SymfonyMailerService;
use App\Infrastructure\Mail\SymfonyMailerServiceFactory;
use App\Infrastructure\Persistence\Repository\EloquentAccountRepository;
use App\Infrastructure\Persistence\Repository\EloquentWithdrawRepository;

return [
    AccountRepositoryInterface::class => EloquentAccountRepository::class,
    WithdrawRepositoryInterface::class => EloquentWithdrawRepository::class,
    EventDispatcherInterface::class => HyperfEventDispatcherAdapter::class,
    SymfonyMailerService::class => SymfonyMailerServiceFactory::class,
];
