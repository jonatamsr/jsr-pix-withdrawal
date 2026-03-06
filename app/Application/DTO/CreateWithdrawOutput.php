<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class CreateWithdrawOutput
{
    public function __construct(
        public string $id,
        public string $accountId,
        public string $method,
        public float $amount,
        public bool $scheduled,
        public ?string $scheduledFor,
        public bool $done,
        public string $createdAt,
    ) {
    }
}
