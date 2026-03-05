<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidWithdrawMethodException extends BusinessException
{
    public function __construct(string $method)
    {
        parent::__construct("The withdraw method {$method} is not supported");
    }

    public function getErrorCode(): string
    {
        return 'INVALID_WITHDRAW_METHOD';
    }
}
