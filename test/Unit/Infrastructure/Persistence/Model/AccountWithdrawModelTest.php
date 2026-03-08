<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Persistence\Model;

use App\Infrastructure\Persistence\Model\AccountWithdrawModel;
use App\Infrastructure\Persistence\Model\Model;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AccountWithdrawModelTest extends TestCase
{
    use UsesMockery;

    private AccountWithdrawModel|MockInterface $model;

    protected function setUp(): void
    {
        $this->model = Mockery::mock(AccountWithdrawModel::class)->makePartial();
    }

    #[Test]
    public function withdrawMethodRelationReturnsRelationBasedOnMethod(): void
    {
        $relatedModel = Mockery::mock(Model::class);

        $this->model->method = 'pix';

        $this->model
            ->shouldReceive('getRelationValue')
            ->once()
            ->with('pix')
            ->andReturn($relatedModel);

        $result = $this->model->withdrawMethodRelation();

        $this->assertSame($relatedModel, $result);
    }

    #[Test]
    public function withdrawMethodRelationReturnsNullWhenNoRelation(): void
    {
        $this->model->method = 'pix';

        $this->model
            ->shouldReceive('getRelationValue')
            ->once()
            ->with('pix')
            ->andReturnNull();

        $result = $this->model->withdrawMethodRelation();

        $this->assertNull($result);
    }
}
