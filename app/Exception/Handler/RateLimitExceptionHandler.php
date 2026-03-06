<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Domain\Exception\RateLimitException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class RateLimitExceptionHandler extends ExceptionHandler
{
    use FormatsErrorResponse;

    private const int RETRY_AFTER_SECONDS = 1;

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        assert($throwable instanceof RateLimitException);

        return $this->errorResponse(
            response: $response,
            status: $throwable->getHttpStatusCode(),
            code: $throwable->getErrorCode(),
            message: $throwable->getMessage(),
        )->withHeader('Retry-After', (string) self::RETRY_AFTER_SECONDS);
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof RateLimitException;
    }
}
