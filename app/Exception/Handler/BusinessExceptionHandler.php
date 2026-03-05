<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Domain\Exception\BusinessException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class BusinessExceptionHandler extends ExceptionHandler
{
    use FormatsErrorResponse;

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();

        /** @var BusinessException $throwable */
        return $this->errorResponse(
            response: $response,
            status: $throwable->getHttpStatusCode(),
            code: $throwable->getErrorCode(),
            message: $throwable->getMessage(),
        );
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
