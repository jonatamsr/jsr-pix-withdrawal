<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Controller;

use App\Application\DTO\CreateWithdrawInput;
use App\Application\DTO\CreateWithdrawOutput;
use App\Application\UseCase\CreateWithdrawUseCase;
use App\Controller\AccountWithdrawController;
use App\Request\CreateWithdrawRequest;
use DomainException;
use Hyperf\HttpServer\Contract\ResponseInterface;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use ReflectionClass;

/**
 * @internal
 */
class AccountWithdrawControllerTest extends TestCase
{
    use UsesMockery;

    private CreateWithdrawUseCase|MockInterface $useCase;

    private MockInterface|ResponseInterface $response;

    private AccountWithdrawController $controller;

    protected function setUp(): void
    {
        $this->useCase = Mockery::mock(CreateWithdrawUseCase::class);
        $this->response = Mockery::mock(ResponseInterface::class);

        $this->controller = new AccountWithdrawController($this->useCase);

        $reflection = new ReflectionClass($this->controller);
        $property = $reflection->getProperty('response');
        $property->setValue($this->controller, $this->response);
    }

    // -- successful immediate withdrawal --

    #[Test]
    public function withdrawReturns201WithOutputOnSuccess(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';

        $request = $this->mockRequest([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 150.75,
            'schedule' => null,
        ]);

        $output = new CreateWithdrawOutput(
            id: '660e8400-e29b-41d4-a716-446655440001',
            accountId: $accountId,
            method: 'pix',
            amount: 150.75,
            scheduled: false,
            scheduledFor: null,
            done: true,
        );

        $this->useCase->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (CreateWithdrawInput $input) use ($accountId): bool {
                return $input->accountId === $accountId
                    && $input->method === 'pix'
                    && $input->methodData === ['type' => 'email', 'key' => 'user@example.com']
                    && $input->amount === 150.75
                    && $input->schedule === null;
            }))
            ->andReturn($output);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->with(Mockery::on(function (array $body) use ($accountId): bool {
                return $body['id'] === '660e8400-e29b-41d4-a716-446655440001'
                    && $body['account_id'] === $accountId
                    && $body['method'] === 'pix'
                    && $body['amount'] === 150.75
                    && $body['scheduled'] === false
                    && $body['scheduled_for'] === null
                    && $body['done'] === true;
            }))
            ->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')
            ->once()
            ->with(201)
            ->andReturn($psrResponse);

        $result = $this->controller->withdraw($accountId, $request);

        $this->assertSame($psrResponse, $result);
    }

    // -- successful scheduled withdrawal --

    #[Test]
    public function withdrawPassesScheduleDateToUseCase(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';

        $request = $this->mockRequest([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 200.00,
            'schedule' => '2026-03-10 14:30',
        ]);

        $output = new CreateWithdrawOutput(
            id: '770e8400-e29b-41d4-a716-446655440002',
            accountId: $accountId,
            method: 'pix',
            amount: 200.00,
            scheduled: true,
            scheduledFor: '2026-03-10 14:30:00',
            done: false,
        );

        $this->useCase->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (CreateWithdrawInput $input): bool {
                return $input->schedule === '2026-03-10 14:30';
            }))
            ->andReturn($output);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->with(Mockery::on(function (array $body): bool {
                return $body['scheduled'] === true
                    && $body['scheduled_for'] === '2026-03-10 14:30:00'
                    && $body['done'] === false;
            }))
            ->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')
            ->once()
            ->with(201)
            ->andReturn($psrResponse);

        $result = $this->controller->withdraw($accountId, $request);

        $this->assertSame($psrResponse, $result);
    }

    // -- input mapping --

    #[Test]
    public function withdrawMapsValidatedDataToCreateWithdrawInput(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';

        $request = $this->mockRequest([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'john@example.com'],
            'amount' => 99.99,
        ]);

        $output = new CreateWithdrawOutput(
            id: '880e8400-e29b-41d4-a716-446655440003',
            accountId: $accountId,
            method: 'pix',
            amount: 99.99,
            scheduled: false,
            scheduledFor: null,
            done: true,
        );

        $this->useCase->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (CreateWithdrawInput $input) use ($accountId): bool {
                return $input->accountId === $accountId
                    && $input->method === 'pix'
                    && $input->methodData === ['type' => 'email', 'key' => 'john@example.com']
                    && $input->amount === 99.99
                    && $input->schedule === null;
            }))
            ->andReturn($output);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $this->response->shouldReceive('json')->andReturn($psrResponse);
        $psrResponse->shouldReceive('withStatus')->with(201)->andReturn($psrResponse);

        $this->controller->withdraw($accountId, $request);
    }

    // -- missing optional pix key defaults to empty array --

    #[Test]
    public function withdrawDefaultsMethodDataToEmptyArrayWhenPixIsMissing(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';

        $request = $this->mockRequest([
            'method' => 'pix',
            'amount' => 50.00,
        ]);

        $output = new CreateWithdrawOutput(
            id: '990e8400-e29b-41d4-a716-446655440004',
            accountId: $accountId,
            method: 'pix',
            amount: 50.00,
            scheduled: false,
            scheduledFor: null,
            done: true,
        );

        $this->useCase->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (CreateWithdrawInput $input): bool {
                return $input->methodData === [];
            }))
            ->andReturn($output);

        $psrResponse = Mockery::mock(PsrResponseInterface::class);
        $this->response->shouldReceive('json')->andReturn($psrResponse);
        $psrResponse->shouldReceive('withStatus')->with(201)->andReturn($psrResponse);

        $this->controller->withdraw($accountId, $request);
    }

    // -- response contains all required fields --

    #[Test]
    public function responseJsonContainsAllRequiredFields(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';

        $request = $this->mockRequest([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 100.00,
        ]);

        $output = new CreateWithdrawOutput(
            id: 'aa0e8400-e29b-41d4-a716-446655440005',
            accountId: $accountId,
            method: 'pix',
            amount: 100.00,
            scheduled: false,
            scheduledFor: null,
            done: true,
        );

        $this->useCase->shouldReceive('execute')->andReturn($output);

        $expectedKeys = ['id', 'account_id', 'method', 'amount', 'scheduled', 'scheduled_for', 'done'];

        $this->response->shouldReceive('json')
            ->once()
            ->with(Mockery::on(function (array $body) use ($expectedKeys): bool {
                foreach ($expectedKeys as $key) {
                    if (! array_key_exists($key, $body)) {
                        return false;
                    }
                }

                return count($body) === count($expectedKeys);
            }))
            ->andReturn($psrResponse = Mockery::mock(PsrResponseInterface::class));

        $psrResponse->shouldReceive('withStatus')->with(201)->andReturn($psrResponse);

        $this->controller->withdraw($accountId, $request);
    }

    // -- use case exception propagates --

    #[Test]
    public function withdrawPropagatesUseCaseException(): void
    {
        $accountId = '550e8400-e29b-41d4-a716-446655440000';

        $request = $this->mockRequest([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 100.00,
        ]);

        $this->useCase->shouldReceive('execute')
            ->once()
            ->andThrow(new DomainException('Account not found'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Account not found');

        $this->controller->withdraw($accountId, $request);
    }

    // -- helper --

    /**
     * @param array<string, mixed> $validated
     */
    private function mockRequest(array $validated): CreateWithdrawRequest|MockInterface
    {
        $request = Mockery::mock(CreateWithdrawRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn($validated);

        return $request;
    }
}
