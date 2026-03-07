<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\DTO\CreateWithdrawInput;
use App\Application\UseCase\CreateWithdrawUseCase;
use App\Request\CreateWithdrawRequest;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class AccountWithdrawController extends AbstractController
{
    public function __construct(
        ResponseInterface $response,
        private readonly CreateWithdrawUseCase $useCase,
    ) {
        parent::__construct($response);
    }

    public function withdraw(string $accountId, CreateWithdrawRequest $request): PsrResponseInterface
    {
        $validated = $request->validated();

        $input = new CreateWithdrawInput(
            accountId: $accountId,
            method: $validated['method'],
            methodData: $validated['pix'] ?? [],
            amount: (float) $validated['amount'],
            schedule: $validated['schedule'] ?? null,
        );

        $output = $this->useCase->execute($input);

        return $this->response->json([
            'id' => $output->id,
            'account_id' => $output->accountId,
            'method' => $output->method,
            'amount' => $output->amount,
            'scheduled' => $output->scheduled,
            'scheduled_for' => $output->scheduledFor,
            'done' => $output->done,
        ])->withStatus(201);
    }
}
