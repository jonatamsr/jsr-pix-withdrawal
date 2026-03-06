<?php

declare(strict_types=1);

use Hyperf\Validation\Middleware\ValidationMiddleware;

return [
    'http' => [
        ValidationMiddleware::class,
    ],
];
