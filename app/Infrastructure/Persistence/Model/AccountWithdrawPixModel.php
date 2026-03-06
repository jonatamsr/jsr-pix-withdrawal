<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Hyperf\Database\Model\Relations\BelongsTo;

class AccountWithdrawPixModel extends Model
{
    public bool $incrementing = false;

    public bool $timestamps = false;

    protected ?string $table = 'account_withdraw_pix';

    protected string $primaryKey = 'account_withdraw_id';

    protected string $keyType = 'string';

    protected array $fillable = [
        'account_withdraw_id',
        'type',
        'key',
    ];

    public function withdraw(): BelongsTo
    {
        return $this->belongsTo(AccountWithdrawModel::class, 'account_withdraw_id', 'id');
    }
}
