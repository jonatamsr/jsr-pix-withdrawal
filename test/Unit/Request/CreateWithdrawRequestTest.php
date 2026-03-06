<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Request;

use App\Request\CreateWithdrawRequest;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\ValidatorInterface;
use Hyperf\Validation\ValidatorFactory;
use HyperfTest\Support\UsesMockery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
class CreateWithdrawRequestTest extends TestCase
{
    use UsesMockery;

    private ValidatorFactory $validatorFactory;

    private CreateWithdrawRequest $request;

    protected function setUp(): void
    {
        $translator = Mockery::mock(TranslatorInterface::class);
        $translator->shouldReceive('get')->andReturnUsing(fn (string $key) => $key);
        $translator->shouldReceive('trans')->andReturnUsing(fn (string $key) => $key);
        $translator->shouldReceive('has')->andReturn(false);

        $this->validatorFactory = new ValidatorFactory($translator);
        $this->request = new CreateWithdrawRequest(Mockery::mock(ContainerInterface::class));
    }

    // -- authorize --

    #[Test]
    public function authorizeReturnsTrue(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    // -- valid payload --

    #[Test]
    public function validPayloadPassesValidation(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 100.50,
            'schedule' => null,
        ]);

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function validPayloadWithSchedulePasses(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 0.01,
            'schedule' => '2026-03-10 14:30',
        ]);

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function validPayloadWithoutSchedulePasses(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 50.00,
        ]);

        $this->assertFalse($validator->fails());
    }

    // -- method field --

    #[Test]
    public function failsWhenMethodIsMissing(): void
    {
        $validator = $this->validate([
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 10.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('method', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenMethodIsNotString(): void
    {
        $validator = $this->validate([
            'method' => 123,
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 10.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('method', $validator->errors()->getMessages());
    }

    // -- pix.type field --

    #[Test]
    public function failsWhenPixTypeIsMissing(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['key' => 'user@example.com'],
            'amount' => 10.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pix.type', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenPixTypeIsInvalid(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'cpf', 'key' => 'user@example.com'],
            'amount' => 10.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pix.type', $validator->errors()->getMessages());
    }

    // -- pix.key field --

    #[Test]
    public function failsWhenPixKeyIsMissing(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email'],
            'amount' => 10.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pix.key', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenPixKeyIsNotValidEmail(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'not-an-email'],
            'amount' => 10.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pix.key', $validator->errors()->getMessages());
    }

    // -- amount field --

    #[Test]
    public function failsWhenAmountIsMissing(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenAmountIsNotNumeric(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 'abc',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenAmountIsZero(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 0,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenAmountIsNegative(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => -5.00,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->getMessages());
    }

    #[Test]
    public function failsWhenAmountIsBelowMinimum(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 0.001,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->getMessages());
    }

    // -- schedule field --

    #[Test]
    public function failsWhenScheduleHasInvalidFormat(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 10.00,
            'schedule' => '06/03/2026',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('schedule', $validator->errors()->getMessages());
    }

    #[Test]
    public function passesWhenScheduleIsNull(): void
    {
        $validator = $this->validate([
            'method' => 'pix',
            'pix' => ['type' => 'email', 'key' => 'user@example.com'],
            'amount' => 10.00,
            'schedule' => null,
        ]);

        $this->assertArrayNotHasKey('schedule', $validator->errors()->getMessages());
    }

    // -- multiple errors --

    #[Test]
    public function returnsMultipleFieldErrors(): void
    {
        $validator = $this->validate([]);

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->getMessages();

        $this->assertArrayHasKey('method', $errors);
        $this->assertArrayHasKey('pix.type', $errors);
        $this->assertArrayHasKey('pix.key', $errors);
        $this->assertArrayHasKey('amount', $errors);
    }

    private function validate(array $data): ValidatorInterface
    {
        return $this->validatorFactory->make($data, $this->request->rules());
    }
}
