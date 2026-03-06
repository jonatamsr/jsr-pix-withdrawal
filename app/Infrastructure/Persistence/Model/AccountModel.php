<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

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
