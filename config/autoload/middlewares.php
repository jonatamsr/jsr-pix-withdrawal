<?php

declare(strict_types=1);

use Hyperf\Tracer\Middleware\TraceMiddleware;
use Hyperf\Validation\Middleware\ValidationMiddleware;

return [
    'http' => [
        TraceMiddleware::class,
        ValidationMiddleware::class,
    ],
];
