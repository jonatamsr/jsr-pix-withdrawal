<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ValidationExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        assert($throwable instanceof ValidationException);

        $body = json_encode([
            'code' => 'VALIDATION_ERROR',
            'message' => $throwable->getMessage(),
            'details' => $throwable->validator->errors()->getMessages(),
        ]);

        return $response
            ->withStatus($throwable->status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream($body));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }
}
