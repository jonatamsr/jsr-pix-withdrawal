<?php

declare(strict_types=1);

use App\Infrastructure\Observability\TraceContextProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

/*
 * This file is part of Hyperf.
 *
 * @see     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

return [
    'default' => [
        'handler' => [
            'class' => StreamHandler::class,
            'constructor' => [
                'stream' => 'php://stdout',
                'level' => Level::Debug,
            ],
        ],
        'formatter' => [
            'class' => JsonFormatter::class,
            'constructor' => [],
        ],
        'processors' => [
            [
                'class' => TraceContextProcessor::class,
            ],
        ],
    ],
];
