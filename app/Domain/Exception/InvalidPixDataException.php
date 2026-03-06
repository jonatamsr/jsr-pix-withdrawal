<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidPixDataException extends BusinessException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function emptyKey(): self
    {
        return new self('PIX key cannot be empty');
    }

    public static function invalidType(string $type): self
    {
        return new self("Invalid PIX key type: {$type}");
    }

    public static function invalidEmail(string $key): self
    {
        return new self("Invalid email for PIX key: {$key}");
    }

    public function getErrorCode(): string
    {
        return 'INVALID_PIX_DATA';
    }
}
