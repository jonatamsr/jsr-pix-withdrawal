<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Exception\InsufficientBalanceException;
use App\Domain\Exception\InvalidAmountException;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;

class Account
{
    private function __construct(
        private readonly Uuid $id,
        private readonly string $name,
        private Money $balance
    ) {
    }

    public static function create(Uuid $id, string $name, Money $balance): self
    {
        return new self($id, $name, $balance);
    }

    public function withdraw(Money $amount): void
    {
        if ($amount->isZero()) {
            throw InvalidAmountException::mustBePositive();
        }

        if (! $this->balance->isGreaterThanOrEqual($amount)) {
            throw new InsufficientBalanceException();
        }

        $this->balance = $this->balance->subtract($amount);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function balance(): Money
    {
        return $this->balance;
    }
}
