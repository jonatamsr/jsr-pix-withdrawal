<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Mail;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Mail\SymfonyMailerService;
use App\Infrastructure\Mail\Template\WithdrawCompletedEmailTemplate;
use DateTimeImmutable;
use HyperfTest\Support\UsesMockery;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
class SymfonyMailerServiceTest extends TestCase
{
    use UsesMockery;

    private MailerInterface|MockInterface $mailer;

    private MockInterface|WithdrawCompletedEmailTemplate $template;

    private SymfonyMailerService $service;

    protected function setUp(): void
    {
        $this->mailer = Mockery::mock(MailerInterface::class);
        $this->template = Mockery::mock(WithdrawCompletedEmailTemplate::class);
        $this->service = new SymfonyMailerService($this->mailer, 'no-reply@test.local', $this->template);
    }

    #[Test]
    public function sendWithdrawCompletedBuildsEmailFromTemplateAndSends(): void
    {
        $withdraw = $this->createWithdraw('150.75');
        $pix = $this->createPix('user@example.com');
        $processedAt = new DateTimeImmutable();
        $expectedEmail = new Email();

        $this->template->shouldReceive('build')
            ->once()
            ->with('no-reply@test.local', $withdraw, $pix, $processedAt)
            ->andReturn($expectedEmail);

        $this->mailer->shouldReceive('send')
            ->once()
            ->with($expectedEmail);

        $this->service->sendWithdrawCompleted($withdraw, $pix, $processedAt);
    }

    private function createWithdraw(string $amount): AccountWithdraw
    {
        return AccountWithdraw::createImmediate(
            Uuid::generate(),
            Uuid::generate(),
            WithdrawMethod::PIX,
            Money::fromString($amount),
        );
    }

    private function createPix(string $email): AccountWithdrawPix
    {
        return AccountWithdrawPix::create(
            Uuid::generate(),
            PixKey::create('email', $email),
        );
    }
}
