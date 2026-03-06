<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\DTO\CreateWithdrawInput;
use App\Application\UseCase\CreateWithdrawUseCase;
use App\Request\CreateWithdrawRequest;
use Psr\Http\Message\ResponseInterface;

class AccountWithdrawController extends AbstractController
{
    public function __construct(
        private readonly CreateWithdrawUseCase $useCase,
    ) {
    }

    public function withdraw(string $accountId, CreateWithdrawRequest $request): ResponseInterface
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
            'created_at' => $output->createdAt,
        ])->withStatus(201);
    }
}
