<?php

declare(strict_types=1);

use App\Controller\AccountWithdrawController;
use App\Controller\HealthController;
use App\Middleware\IdempotencyMiddleware;
use App\Middleware\RateLimitMiddleware;
use Hyperf\HttpServer\Router\Router;

Router::get('/health', [HealthController::class, 'check']);

Router::post('/account/{accountId}/balance/withdraw', [AccountWithdrawController::class, 'withdraw'], [
    'middleware' => [IdempotencyMiddleware::class, RateLimitMiddleware::class],
]);
