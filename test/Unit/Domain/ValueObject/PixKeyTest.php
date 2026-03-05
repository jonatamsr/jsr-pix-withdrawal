<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use App\Domain\Enum\PixKeyType;
use App\Domain\ValueObject\PixKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class PixKeyTest extends TestCase
{
    // -- Construction --

    #[Test]
    public function createWithValidEmailKey(): void
    {
        $pixKey = PixKey::create('email', 'someone@email.com');

        $this->assertSame(PixKeyType::EMAIL, $pixKey->type());
        $this->assertSame('someone@email.com', $pixKey->key());
    }

    #[Test]
    public function createTrimsWhitespace(): void
    {
        $pixKey = PixKey::create('email', '  someone@email.com  ');

        $this->assertSame('someone@email.com', $pixKey->key());
    }

    // -- Type normalization --

    #[Test]
    public function createNormalizesUppercaseType(): void
    {
        $pixKey = PixKey::create('EMAIL', 'someone@email.com');

        $this->assertSame(PixKeyType::EMAIL, $pixKey->type());
    }

    #[Test]
    public function createNormalizesMixedCaseType(): void
    {
        $pixKey = PixKey::create('Email', 'someone@email.com');

        $this->assertSame(PixKeyType::EMAIL, $pixKey->type());
    }

    // -- Invalid type --

    #[Test]
    public function createThrowsOnInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PIX key type: phone');

        PixKey::create('phone', '+5511999999999');
    }

    #[Test]
    public function createThrowsOnEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PIX key type');

        PixKey::create('', 'someone@email.com');
    }

    // -- Validation delegation --

    #[Test]
    public function createDelegatesToPixKeyTypeValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PixKey::create('email', 'not-an-email');
    }

    #[Test]
    public function createThrowsOnWhitespaceOnlyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PIX key cannot be empty');

        PixKey::create('email', '   ');
    }

    // -- Getters --

    #[Test]
    public function typeReturnsPixKeyType(): void
    {
        $pixKey = PixKey::create('email', 'test@example.com');

        $this->assertInstanceOf(PixKeyType::class, $pixKey->type());
        $this->assertSame(PixKeyType::EMAIL, $pixKey->type());
    }

    #[Test]
    public function keyReturnsKeyString(): void
    {
        $pixKey = PixKey::create('email', 'test@example.com');

        $this->assertSame('test@example.com', $pixKey->key());
    }
}
