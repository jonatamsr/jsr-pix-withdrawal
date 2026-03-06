<?php

declare(strict_types=1);

use Hyperf\RateLimit\Storage\RedisStorage;

return [
    'create' => 1,
    'consume' => 1,
    'capacity' => 10,
    'storage' => [
        'class' => RedisStorage::class,
        'options' => [
            'pool' => 'default',
            'expired_time' => 0,
        ],
    ],
];
