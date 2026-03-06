<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $balance
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 */
class AccountModel extends Model
{
    public bool $incrementing = false;

    protected ?string $table = 'account';

    protected string $keyType = 'string';

    protected array $fillable = [
        'id',
        'name',
        'balance',
    ];

    protected array $casts = [
        'balance' => 'decimal:2',
    ];
}
