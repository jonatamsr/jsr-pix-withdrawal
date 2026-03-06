<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Exception\InvalidWithdrawMethodException;
use App\Domain\Strategy\PixWithdrawStrategy;
use App\Domain\Strategy\WithdrawMethodStrategyInterface;

final class WithdrawMethodFactory
{
    /** @var array<string, class-string<WithdrawMethodStrategyInterface>> */
    private const STRATEGIES = [
        'pix' => PixWithdrawStrategy::class,
    ];

    public function create(string $method): WithdrawMethodStrategyInterface
    {
        $normalized = strtolower(trim($method));

        $strategyClass = self::STRATEGIES[$normalized] ?? null;

        if ($strategyClass === null) {
            throw new InvalidWithdrawMethodException($method);
        }

        return new $strategyClass();
    }
}
