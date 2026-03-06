<?php

declare(strict_types=1);

namespace App\Infrastructure\EventDispatcher;

use App\Domain\Event\DomainEvent;
use App\Domain\Port\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

class HyperfEventDispatcherAdapter implements EventDispatcherInterface
{
    public function __construct(
        private readonly PsrEventDispatcherInterface $dispatcher,
    ) {}

    public function dispatch(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
