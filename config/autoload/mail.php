<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'host' => env('MAIL_HOST', 'mailhog'),
    'port' => (int) env('MAIL_PORT', 1025),
    'from' => env('MAIL_FROM_ADDRESS', 'no-reply@jsr-pix-withdrawal.local'),
];
