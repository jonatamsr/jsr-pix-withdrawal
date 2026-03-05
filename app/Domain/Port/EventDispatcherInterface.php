<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Event\DomainEvent;

interface EventDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}
