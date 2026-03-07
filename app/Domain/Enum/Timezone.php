<?php

declare(strict_types=1);

namespace App\Domain\Enum;

use DateTimeZone;

enum Timezone: string
{
    case STORAGE = 'UTC';
    case CLIENT = 'America/Sao_Paulo';

    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->value);
    }
}
