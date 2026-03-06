<?php

declare(strict_types=1);

namespace App\Domain\Enum;

use App\Domain\Exception\InvalidPixDataException;

enum PixKeyType: string
{
    case EMAIL = 'email';

    public function validate(string $key): void
    {
        if ($key === '') {
            throw InvalidPixDataException::emptyKey();
        }

        match ($this) {
            self::EMAIL => self::validateEmail($key),
        };
    }

    private static function validateEmail(string $key): void
    {
        if (filter_var($key, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidPixDataException::invalidEmail($key);
        }
    }
}
