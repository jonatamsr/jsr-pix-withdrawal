<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class RateLimitException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Too many requests. Please try again later');
    }

    public function getErrorCode(): string
    {
        return 'RATE_LIMIT_EXCEEDED';
    }

    public function getHttpStatusCode(): int
    {
        return 429;
    }
}
