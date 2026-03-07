<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Enum\WithdrawMethod;
use Psr\Container\ContainerInterface;

class WithdrawNotificationStrategyFactory
{
    /** @var array<string, class-string<WithdrawNotificationStrategyInterface>> */
    private const STRATEGIES = [
        WithdrawMethod::PIX->value => PixWithdrawNotificationStrategy::class,
    ];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function create(WithdrawMethod $method): ?WithdrawNotificationStrategyInterface
    {
        $strategyClass = self::STRATEGIES[$method->value] ?? null;

        if ($strategyClass === null) {
            return null; // @codeCoverageIgnore
        }

        return $this->container->get($strategyClass);
    }
}
