<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum WithdrawMethod: string
{
    case PIX = 'pix';
}
