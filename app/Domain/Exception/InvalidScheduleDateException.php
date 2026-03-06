<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidScheduleDateException extends BusinessException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function inThePast(): self
    {
        return new self('The schedule date must be in the future');
    }

    public static function invalidFormat(string $value): self
    {
        return new self("Invalid schedule date format: {$value}. Expected format: Y-m-d H:i");
    }

    public function getErrorCode(): string
    {
        return 'INVALID_SCHEDULE_DATE';
    }
}
