<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;

class AccountWithdrawPix
{
    private function __construct(
        private readonly Uuid $accountWithdrawId,
        private readonly PixKey $pixKey
    ) {
    }

    public static function create(Uuid $accountWithdrawId, PixKey $pixKey): self
    {
        return new self($accountWithdrawId, $pixKey);
    }

    public function accountWithdrawId(): Uuid
    {
        return $this->accountWithdrawId;
    }

    public function pixKey(): PixKey
    {
        return $this->pixKey;
    }
}
