<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Enum;

use App\Domain\Enum\PixKeyType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class PixKeyTypeTest extends TestCase
{
    // -- EMAIL validation --

    #[Test]
    public function emailValidatesSuccessfully(): void
    {
        $this->expectNotToPerformAssertions();

        PixKeyType::EMAIL->validate('someone@email.com');
    }

    #[Test]
    public function emailThrowsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email for PIX key');

        PixKeyType::EMAIL->validate('not-an-email');
    }

    #[Test]
    public function emailThrowsOnEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PIX key cannot be empty');

        PixKeyType::EMAIL->validate('');
    }

    #[Test]
    public function emailThrowsOnMissingDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email for PIX key');

        PixKeyType::EMAIL->validate('someone@');
    }

    #[Test]
    public function emailThrowsOnMissingLocalPart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email for PIX key');

        PixKeyType::EMAIL->validate('@email.com');
    }
}
