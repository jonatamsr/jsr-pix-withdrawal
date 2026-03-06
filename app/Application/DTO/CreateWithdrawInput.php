<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class CreateWithdrawInput
{
    /**
     * @param array<string, mixed> $methodData
     */
    public function __construct(
        public string $accountId,
        public string $method,
        public array $methodData,
        public float $amount,
        public ?string $schedule = null,
    ) {}
}
