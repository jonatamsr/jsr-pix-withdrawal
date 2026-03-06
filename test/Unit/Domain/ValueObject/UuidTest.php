<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidUuidException;
use App\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class UuidTest extends TestCase
{
    #[Test]
    public function generateCreatesValidUuid(): void
    {
        $uuid = Uuid::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid->value()
        );
    }

    #[Test]
    public function fromStringWithValidUuid(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = Uuid::fromString($value);

        $this->assertSame($value, $uuid->value());
    }

    #[Test]
    public function fromStringThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidUuidException::class);
        $this->expectExceptionMessage('Invalid UUID format');

        Uuid::fromString('not-a-uuid');
    }

    #[Test]
    public function fromStringThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidUuidException::class);

        Uuid::fromString('');
    }

    #[Test]
    public function valueReturnsStringRepresentation(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        $uuid = Uuid::fromString($value);

        $this->assertSame($value, $uuid->value());
    }

    #[Test]
    public function equalsReturnsTrueForSameUuid(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        $a = Uuid::fromString($value);
        $b = Uuid::fromString($value);

        $this->assertEquals($a, $b);
    }

    #[Test]
    public function equalsReturnsFalseForDifferentUuids(): void
    {
        $a = Uuid::generate();
        $b = Uuid::generate();

        $this->assertNotEquals($a, $b);
    }

    #[Test]
    public function twoGeneratedUuidsAreDifferent(): void
    {
        $a = Uuid::generate();
        $b = Uuid::generate();

        $this->assertNotSame($a->value(), $b->value());
    }
}
