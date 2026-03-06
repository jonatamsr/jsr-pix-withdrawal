<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidAmountException;
use InvalidArgumentException;

final readonly class Money
{
    private function __construct(private int $cents)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromFloat(float $value): self
    {
        if ($value < 0) {
            throw InvalidAmountException::negative();
        }

        if (round($value, 2) != $value) {
            throw InvalidAmountException::tooManyDecimals();
        }

        return new self((int) round($value * 100));
    }

    public static function fromString(string $value): self
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw InvalidAmountException::invalidFormat($value);
        }

        $parts = explode('.', $value);

        $reais = (int) $parts[0];
        $cents = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';

        return new self($reais * 100 + (int) $cents);
    }

    public function toDecimal(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    public function subtract(self $otherAmount): self
    {
        $result = $this->cents - $otherAmount->cents;

        if ($result < 0) {
            $resultDecimal = number_format($result / 100, 2, '.', '');
            throw new InvalidArgumentException(
                $message = 'Subtraction would result in negative value:'
                    . " {$this->toDecimal()} - {$otherAmount->toDecimal()} = {$resultDecimal}"
            );
        }

        return new self($result);
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->cents >= $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }
}
