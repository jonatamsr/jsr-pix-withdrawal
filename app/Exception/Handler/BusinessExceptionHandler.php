<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Domain\Exception\BusinessException;
use App\Domain\Exception\InsufficientBalanceException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Status;
use Throwable;

class BusinessExceptionHandler extends ExceptionHandler
{
    use FormatsErrorResponse;

    private const STATUS_MAP = [
        InsufficientBalanceException::class => Status::UNPROCESSABLE_ENTITY,
    ];

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();

        /** @var BusinessException $throwable */
        $status = self::STATUS_MAP[$throwable::class] ?? Status::BAD_REQUEST;

        return $this->errorResponse(
            response: $response,
            status: $status,
            code: $throwable->getErrorCode(),
            message: $throwable->getMessage()
        );
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
