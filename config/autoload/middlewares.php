<?php

declare(strict_types=1);

use App\Middleware\RequestIdMiddleware;
use Hyperf\Tracer\Middleware\TraceMiddleware;
use Hyperf\Validation\Middleware\ValidationMiddleware;

return [
    'http' => [
        RequestIdMiddleware::class,
        TraceMiddleware::class,
        ValidationMiddleware::class,
    ],
];
