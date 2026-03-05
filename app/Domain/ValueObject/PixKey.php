<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Enum\PixKeyType;
use InvalidArgumentException;

final readonly class PixKey
{
    private function __construct(
        private PixKeyType $type,
        private string $key
    ) {
    }

    public static function create(string $type, string $key): self
    {
        $normalizedType = strtolower($type);

        $pixKeyType = PixKeyType::tryFrom($normalizedType)
            ?? throw new InvalidArgumentException("Invalid PIX key type: {$type}");

        $key = trim($key);

        $pixKeyType->validate($key);

        return new self($pixKeyType, $key);
    }

    public function type(): PixKeyType
    {
        return $this->type;
    }

    public function key(): string
    {
        return $this->key;
    }
}
