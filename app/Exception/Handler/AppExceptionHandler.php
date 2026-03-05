<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    use FormatsErrorResponse;

    public function __construct(protected StdoutLoggerInterface $logger) {}

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();

        $this->logger->error(
            message: $throwable->getMessage(),
            context: [
                'exception' => $throwable::class,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ]
        );

        return $this->errorResponse(
            response: $response,
            status: 500,
            code: 'INTERNAL_ERROR',
            message: 'An unexpected error occurred'
        );
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
