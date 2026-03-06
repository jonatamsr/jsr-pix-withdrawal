<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasOne;

/**
 * @property string $id
 * @property string $account_id
 * @property string $method
 * @property string $amount
 * @property bool $scheduled
 * @property null|Carbon $scheduled_for
 * @property bool $done
 * @property bool $error
 * @property null|string $error_reason
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 * @property AccountModel $account
 * @property null|AccountWithdrawPixModel $pix
 */
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
