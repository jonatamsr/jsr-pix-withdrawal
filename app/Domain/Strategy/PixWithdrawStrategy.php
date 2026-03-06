<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

use App\Domain\ValueObject\PixKey;

final class PixWithdrawStrategy implements WithdrawMethodStrategyInterface
{
    public function validateAndBuild(array $data): WithdrawMethodData
    {
        return new PixWithdrawData(
            PixKey::create($data['type'] ?? '', $data['key'] ?? '')
        );
    }
}
