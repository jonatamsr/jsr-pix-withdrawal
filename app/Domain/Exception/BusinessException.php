<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use DomainException;

abstract class BusinessException extends DomainException
{
    abstract public function getErrorCode(): string;

    public function getHttpStatusCode(): int
    {
        return 422;
    }
}
