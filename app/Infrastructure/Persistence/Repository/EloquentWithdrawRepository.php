<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Port\WithdrawRepositoryInterface;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\Strategy\WithdrawMethodData;
use App\Infrastructure\Persistence\Mapper\WithdrawMapper;
use App\Infrastructure\Persistence\Model\AccountWithdrawModel;
use App\Infrastructure\Persistence\Model\AccountWithdrawPixModel;
use Carbon\Carbon;

class EloquentWithdrawRepository implements WithdrawRepositoryInterface
{
    public function __construct(
        private readonly AccountWithdrawModel $withdrawModel,
        private readonly AccountWithdrawPixModel $pixModel,
        private readonly WithdrawMapper $mapper,
    ) {}

    public function save(AccountWithdraw $withdraw, ?WithdrawMethodData $methodData = null): void
    {
        $data = $this->mapper->toModel($withdraw);

        $this->withdrawModel->newQuery()->updateOrInsert(
            ['id' => $data['id']],
            $data,
        );

        if ($methodData instanceof PixWithdrawData) {
            $pixKey = $methodData->getPixKey();

            $this->pixModel->newQuery()->updateOrInsert(
                ['account_withdraw_id' => $withdraw->id()->value()],
                [
                    'account_withdraw_id' => $withdraw->id()->value(),
                    'type' => $pixKey->type()->value,
                    'key' => $pixKey->key(),
                ],
            );
        }
    }

    /**
     * @return AccountWithdraw[]
     */
    public function findPendingScheduled(): array
    {
        $models = $this->withdrawModel->newQuery()
            ->where('scheduled', true)
            ->where('done', false)
            ->where('error', false)
            ->where('scheduled_for', '<=', Carbon::now())
            ->get();

        return $models->map(fn(AccountWithdrawModel $model) => $this->mapper->toDomain($model))->all();
    }
}
