<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Persistence\Repository;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Persistence\Mapper\WithdrawMapper;
use App\Infrastructure\Persistence\Model\AccountWithdrawModel;
use App\Infrastructure\Persistence\Model\AccountWithdrawPixModel;
use App\Infrastructure\Persistence\Repository\EloquentWithdrawRepository;
use Carbon\Carbon;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EloquentWithdrawRepositoryTest extends TestCase
{
    use UsesMockery;

    private WithdrawMapper $mapper;

    private EloquentWithdrawRepository $repository;

    private AccountWithdrawModel|MockInterface $withdrawModel;

    private AccountWithdrawPixModel|MockInterface $pixModel;

    private Builder|MockInterface $withdrawQueryBuilder;

    private Builder|MockInterface $pixQueryBuilder;

    protected function setUp(): void
    {
        $this->withdrawModel = Mockery::mock(AccountWithdrawModel::class);
        $this->pixModel = Mockery::mock(AccountWithdrawPixModel::class);
        $this->withdrawQueryBuilder = Mockery::mock(Builder::class);
        $this->pixQueryBuilder = Mockery::mock(Builder::class);

        $this->mapper = new WithdrawMapper();
        $this->repository = new EloquentWithdrawRepository(
            $this->withdrawModel,
            $this->pixModel,
            $this->mapper,
        );
    }

    // -- save (without method data) --

    #[Test]
    public function savePersistsWithdrawWithoutMethodData(): void
    {
        $withdraw = AccountWithdraw::createImmediate(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'),
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            WithdrawMethod::PIX,
            Money::fromFloat(150.75),
        );

        $this->withdrawModel->shouldReceive('newQuery')->once()->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('updateOrInsert')
            ->once()
            ->withArgs(function (array $conditions, array $data) {
                return $conditions === ['id' => '550e8400-e29b-41d4-a716-446655440001']
                    && $data['id'] === '550e8400-e29b-41d4-a716-446655440001'
                    && $data['account_id'] === '550e8400-e29b-41d4-a716-446655440000'
                    && $data['method'] === 'pix'
                    && $data['amount'] === '150.75'
                    && $data['done'] === true
                    && $data['scheduled'] === false;
            });

        $this->repository->save($withdraw);
    }

    // -- save (with PIX method data) --

    #[Test]
    public function savePersistsWithdrawAndPixData(): void
    {
        $withdraw = AccountWithdraw::createImmediate(
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'),
            Uuid::fromString('550e8400-e29b-41d4-a716-446655440000'),
            WithdrawMethod::PIX,
            Money::fromFloat(150.75),
        );

        $pixKey = PixKey::create('email', 'fulano@email.com');
        $pixData = new PixWithdrawData($pixKey);

        $this->withdrawModel->shouldReceive('newQuery')->once()->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('updateOrInsert')
            ->once()
            ->withArgs(function (array $conditions, array $data) {
                return $conditions === ['id' => '550e8400-e29b-41d4-a716-446655440001']
                    && $data['method'] === 'pix'
                    && $data['amount'] === '150.75';
            });

        $this->pixModel->shouldReceive('newQuery')->once()->andReturn($this->pixQueryBuilder);
        $this->pixQueryBuilder->shouldReceive('updateOrInsert')
            ->with(
                ['account_withdraw_id' => '550e8400-e29b-41d4-a716-446655440001'],
                [
                    'account_withdraw_id' => '550e8400-e29b-41d4-a716-446655440001',
                    'type' => 'email',
                    'key' => 'fulano@email.com',
                ],
            )
            ->once();

        $this->repository->save($withdraw, $pixData);
    }

    // -- findPendingScheduled --

    #[Test]
    public function findPendingScheduledReturnsOnlyPendingWithdrawals(): void
    {
        $model1 = new AccountWithdrawModel();
        $model1->id = '550e8400-e29b-41d4-a716-446655440010';
        $model1->account_id = '550e8400-e29b-41d4-a716-446655440000';
        $model1->method = 'pix';
        $model1->amount = '100.00';
        $model1->scheduled = true;
        $model1->scheduled_for = '2026-03-01 10:00:00';
        $model1->done = false;
        $model1->error = false;
        $model1->error_reason = null;
        $model1->created_at = '2026-02-28 10:00:00';

        $model2 = new AccountWithdrawModel();
        $model2->id = '550e8400-e29b-41d4-a716-446655440011';
        $model2->account_id = '550e8400-e29b-41d4-a716-446655440000';
        $model2->method = 'pix';
        $model2->amount = '200.00';
        $model2->scheduled = true;
        $model2->scheduled_for = '2026-03-02 15:00:00';
        $model2->done = false;
        $model2->error = false;
        $model2->error_reason = null;
        $model2->created_at = '2026-02-28 12:00:00';

        $collection = new Collection([$model1, $model2]);

        $this->withdrawModel->shouldReceive('newQuery')->once()->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('scheduled', true)
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('done', false)
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('error', false)
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('scheduled_for', '<=', Mockery::type(Carbon::class))
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('get')
            ->once()
            ->andReturn($collection);

        $results = $this->repository->findPendingScheduled();

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(AccountWithdraw::class, $results);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440010', $results[0]->id()->value());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440011', $results[1]->id()->value());
        $this->assertTrue($results[0]->isScheduled());
        $this->assertFalse($results[0]->isDone());
        $this->assertFalse($results[0]->hasError());
    }

    #[Test]
    public function findPendingScheduledReturnsEmptyArrayWhenNoneFound(): void
    {
        $collection = new Collection([]);

        $this->withdrawModel->shouldReceive('newQuery')->once()->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('scheduled', true)
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('done', false)
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('error', false)
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('where')
            ->with('scheduled_for', '<=', Mockery::type(Carbon::class))
            ->once()
            ->andReturn($this->withdrawQueryBuilder);
        $this->withdrawQueryBuilder->shouldReceive('get')
            ->once()
            ->andReturn($collection);

        $results = $this->repository->findPendingScheduled();

        $this->assertCount(0, $results);
        $this->assertSame([], $results);
    }
}
