<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class InvalidScheduleDateException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('The schedule date must be in the future');
    }

    public function getErrorCode(): string
    {
        return 'INVALID_SCHEDULE_DATE';
    }
}
