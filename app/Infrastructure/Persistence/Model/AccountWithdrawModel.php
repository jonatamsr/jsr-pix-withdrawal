<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasOne;

class AccountWithdrawModel extends Model
{
    public bool $incrementing = false;

    protected ?string $table = 'account_withdraw';

    protected string $keyType = 'string';

    protected array $fillable = [
        'id',
        'account_id',
        'method',
        'amount',
        'scheduled',
        'scheduled_for',
        'done',
        'error',
        'error_reason',
    ];

    protected array $casts = [
        'amount' => 'decimal:2',
        'scheduled' => 'boolean',
        'done' => 'boolean',
        'error' => 'boolean',
        'scheduled_for' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountModel::class, 'account_id', 'id');
    }

    public function pix(): HasOne
    {
        return $this->hasOne(AccountWithdrawPixModel::class, 'account_withdraw_id', 'id');
    }
}
