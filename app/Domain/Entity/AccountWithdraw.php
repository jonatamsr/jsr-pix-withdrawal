<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Enum\WithdrawMethod;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

class AccountWithdraw
{
    private function __construct(
        private readonly Uuid $id,
        private readonly Uuid $accountId,
        private readonly WithdrawMethod $method,
        private readonly Money $amount,
        private readonly bool $scheduled,
        private readonly ?DateTimeImmutable $scheduledFor,
        private bool $done,
        private bool $error,
        private ?string $errorReason,
        private readonly DateTimeImmutable $createdAt
    ) {}

    public static function createImmediate(
        Uuid $id,
        Uuid $accountId,
        WithdrawMethod $method,
        Money $amount
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            method: $method,
            amount: $amount,
            scheduled: false,
            scheduledFor: null,
            done: true,
            error: false,
            errorReason: null,
            createdAt: new DateTimeImmutable()
        );
    }

    public static function createScheduled(
        Uuid $id,
        Uuid $accountId,
        WithdrawMethod $method,
        Money $amount,
        DateTimeImmutable $scheduledFor
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            method: $method,
            amount: $amount,
            scheduled: true,
            scheduledFor: $scheduledFor,
            done: false,
            error: false,
            errorReason: null,
            createdAt: new DateTimeImmutable()
        );
    }

    public static function reconstitute(
        Uuid $id,
        Uuid $accountId,
        WithdrawMethod $method,
        Money $amount,
        bool $scheduled,
        ?DateTimeImmutable $scheduledFor,
        bool $done,
        bool $error,
        ?string $errorReason,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: $id,
            accountId: $accountId,
            method: $method,
            amount: $amount,
            scheduled: $scheduled,
            scheduledFor: $scheduledFor,
            done: $done,
            error: $error,
            errorReason: $errorReason,
            createdAt: $createdAt
        );
    }

    public function markAsDone(): void
    {
        $this->done = true;
        $this->error = false;
        $this->errorReason = null;
    }

    public function markAsFailed(string $reason): void
    {
        $this->done = false;
        $this->error = true;
        $this->errorReason = $reason;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function method(): WithdrawMethod
    {
        return $this->method;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled;
    }

    public function scheduledFor(): ?DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function hasError(): bool
    {
        return $this->error;
    }

    public function errorReason(): ?string
    {
        return $this->errorReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
