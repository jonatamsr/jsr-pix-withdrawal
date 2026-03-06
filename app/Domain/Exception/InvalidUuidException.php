<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidUuidException extends BusinessException
{
    public function __construct(string $value)
    {
        parent::__construct("Invalid UUID format: {$value}");
    }

    public function getErrorCode(): string
    {
        return 'INVALID_UUID';
    }

    public function getHttpStatusCode(): int
    {
        return 400;
    }
}
