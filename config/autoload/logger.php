<?php

declare(strict_types=1);

use App\Infrastructure\Observability\TraceContextProcessor;
use Monolog\Formatter\LineFormatter;
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
                'level' => Level::Info,
            ],
        ],
        'formatter' => [
            'class' => LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ],
        'processors' => [
            [
                'class' => TraceContextProcessor::class,
            ],
        ],
    ],
];
