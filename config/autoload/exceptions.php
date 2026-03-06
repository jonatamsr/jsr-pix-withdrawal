<?php

declare(strict_types=1);

use App\Exception\Handler\AppExceptionHandler;
use App\Exception\Handler\BusinessExceptionHandler;
use App\Exception\Handler\RateLimitExceptionHandler;
use App\Exception\Handler\ValidationExceptionHandler;
use Hyperf\HttpServer\Exception\Handler\HttpExceptionHandler;

return [
    'handler' => [
        'http' => [
            ValidationExceptionHandler::class,
            RateLimitExceptionHandler::class,
            BusinessExceptionHandler::class,
            HttpExceptionHandler::class,
            AppExceptionHandler::class,
        ],
    ],
];
