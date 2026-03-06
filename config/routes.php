<?php

declare(strict_types=1);

use App\Controller\AccountWithdrawController;
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::post('/account/{accountId}/balance/withdraw', [AccountWithdrawController::class, 'withdraw']);

Router::get('/favicon.ico', function () {
    return '';
});
