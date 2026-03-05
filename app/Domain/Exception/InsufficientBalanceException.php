<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InsufficientBalanceException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('Insufficient balance to complete this withdrawal');
    }

    public function getErrorCode(): string
    {
        return 'INSUFFICIENT_BALANCE';
    }
}
