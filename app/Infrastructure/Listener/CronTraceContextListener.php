<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use Hyperf\Context\Context;
use Hyperf\Crontab\Event\AfterExecute;
use Hyperf\Crontab\Event\BeforeExecute;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Ramsey\Uuid\Uuid;

#[Listener]
class CronTraceContextListener implements ListenerInterface
{
    public const string TRACE_ID_KEY = 'trace_id';

    public function listen(): array
    {
        return [
            BeforeExecute::class,
            AfterExecute::class,
            FailToExecute::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof BeforeExecute) {
            Context::set(self::TRACE_ID_KEY, Uuid::uuid4()->toString());

            return;
        }

        if ($event instanceof AfterExecute || $event instanceof FailToExecute) {
            Context::destroy(self::TRACE_ID_KEY);
        }
    }
}
