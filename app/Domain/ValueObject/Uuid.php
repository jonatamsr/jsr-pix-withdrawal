<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Uuid
{
    private function __construct(private UuidInterface $uuid)
    {
    }

    public static function generate(): self
    {
        return new self(RamseyUuid::uuid4());
    }

    public static function fromString(string $value): self
    {
        if (! RamseyUuid::isValid($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid UUID format: %s', $value)
            );
        }

        return new self(RamseyUuid::fromString($value));
    }

    public function value(): string
    {
        return $this->uuid->toString();
    }
}
