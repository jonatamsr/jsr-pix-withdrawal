<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidAmountException extends BusinessException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function negative(): self
    {
        return new self('Money cannot be negative');
    }

    public static function tooManyDecimals(): self
    {
        return new self('Money must have at most 2 decimal places');
    }

    public static function invalidFormat(string $value): self
    {
        return new self("Invalid money format: {$value}");
    }

    public static function mustBePositive(): self
    {
        return new self('Withdraw amount must be greater than zero');
    }

    public function getErrorCode(): string
    {
        return 'INVALID_AMOUNT';
    }
}
