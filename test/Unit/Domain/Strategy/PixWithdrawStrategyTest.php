<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Strategy;

use App\Domain\Enum\PixKeyType;
use App\Domain\Exception\InvalidPixDataException;
use App\Domain\Strategy\PixWithdrawData;
use App\Domain\Strategy\PixWithdrawStrategy;
use App\Domain\Strategy\WithdrawMethodData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class PixWithdrawStrategyTest extends TestCase
{
    private PixWithdrawStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new PixWithdrawStrategy();
    }

    // -- Valid PIX email --

    #[Test]
    public function validateAndBuildReturnsPixWithdrawDataForValidEmail(): void
    {
        $result = $this->strategy->validateAndBuild([
            'type' => 'email',
            'key' => 'fulano@email.com',
        ]);

        $this->assertInstanceOf(WithdrawMethodData::class, $result);
        $this->assertInstanceOf(PixWithdrawData::class, $result);
    }

    #[Test]
    public function pixWithdrawDataExposesPixKey(): void
    {
        /** @var PixWithdrawData $result */
        $result = $this->strategy->validateAndBuild([
            'type' => 'email',
            'key' => 'fulano@email.com',
        ]);

        $this->assertSame(PixKeyType::EMAIL, $result->getPixKey()->type());
        $this->assertSame('fulano@email.com', $result->getPixKey()->key());
    }

    // -- Invalid type --

    #[Test]
    public function throwsOnUnsupportedPixType(): void
    {
        $this->expectException(InvalidPixDataException::class);
        $this->expectExceptionMessage('Invalid PIX key type: phone');

        $this->strategy->validateAndBuild([
            'type' => 'phone',
            'key' => '+5511999999999',
        ]);
    }

    #[Test]
    public function throwsOnMissingType(): void
    {
        $this->expectException(InvalidPixDataException::class);
        $this->expectExceptionMessage('Invalid PIX key type');

        $this->strategy->validateAndBuild([
            'key' => 'fulano@email.com',
        ]);
    }

    #[Test]
    public function throwsOnEmptyType(): void
    {
        $this->expectException(InvalidPixDataException::class);
        $this->expectExceptionMessage('Invalid PIX key type');

        $this->strategy->validateAndBuild([
            'type' => '',
            'key' => 'fulano@email.com',
        ]);
    }

    // -- Missing key --

    #[Test]
    public function throwsOnMissingKey(): void
    {
        $this->expectException(InvalidPixDataException::class);
        $this->expectExceptionMessage('PIX key cannot be empty');

        $this->strategy->validateAndBuild([
            'type' => 'email',
        ]);
    }

    #[Test]
    public function throwsOnEmptyKey(): void
    {
        $this->expectException(InvalidPixDataException::class);
        $this->expectExceptionMessage('PIX key cannot be empty');

        $this->strategy->validateAndBuild([
            'type' => 'email',
            'key' => '',
        ]);
    }

    // -- Invalid email format --

    #[Test]
    public function throwsOnInvalidEmailFormat(): void
    {
        $this->expectException(InvalidPixDataException::class);

        $this->strategy->validateAndBuild([
            'type' => 'email',
            'key' => 'not-an-email',
        ]);
    }

    // -- Type normalization --

    #[Test]
    public function acceptsUppercaseType(): void
    {
        $result = $this->strategy->validateAndBuild([
            'type' => 'EMAIL',
            'key' => 'someone@email.com',
        ]);

        $this->assertInstanceOf(PixWithdrawData::class, $result);
    }

    // -- Generic interface contract --

    #[Test]
    public function returnTypeImplementsWithdrawMethodData(): void
    {
        $result = $this->strategy->validateAndBuild([
            'type' => 'email',
            'key' => 'test@example.com',
        ]);

        // Ensures the strategy returns a generic type, not PIX-coupled
        $this->assertInstanceOf(WithdrawMethodData::class, $result);
    }
}
