<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

use App\Domain\ValueObject\PixKey;

final readonly class PixWithdrawData implements WithdrawMethodData
{
    public function __construct(private PixKey $pixKey)
    {
    }

    public function getPixKey(): PixKey
    {
        return $this->pixKey;
    }
}
