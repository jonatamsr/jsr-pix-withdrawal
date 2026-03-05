<?php

declare(strict_types=1);

namespace App\Domain\Enum;

use InvalidArgumentException;

enum PixKeyType: string
{
    case EMAIL = 'email';

    public function validate(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('PIX key cannot be empty');
        }

        match ($this) {
            self::EMAIL => self::validateEmail($key),
        };
    }

    private static function validateEmail(string $key): void
    {
        if (filter_var($key, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email for PIX key: {$key}");
        }
    }
}
