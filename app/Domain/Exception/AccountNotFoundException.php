<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class AccountNotFoundException extends BusinessException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Account with ID {$accountId} was not found");
    }

    public function getErrorCode(): string
    {
        return 'ACCOUNT_NOT_FOUND';
    }

    public function getHttpStatusCode(): int
    {
        return 404;
    }
}
