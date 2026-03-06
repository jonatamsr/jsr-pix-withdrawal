<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Mail\Template;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\Enum\WithdrawMethod;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use App\Infrastructure\Mail\Template\WithdrawCompletedEmailTemplate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class WithdrawCompletedEmailTemplateTest extends TestCase
{
    private WithdrawCompletedEmailTemplate $template;

    protected function setUp(): void
    {
        $this->template = new WithdrawCompletedEmailTemplate();
    }

    #[Test]
    public function buildSetsFromAddress(): void
    {
        $email = $this->template->build(
            'no-reply@test.local',
            $this->createWithdraw('100.00'),
            $this->createPix('user@example.com'),
        );

        $fromAddresses = $email->getFrom();
        $this->assertCount(1, $fromAddresses);
        $this->assertSame('no-reply@test.local', $fromAddresses[0]->getAddress());
    }

    #[Test]
    public function buildSetsToAsPixKey(): void
    {
        $email = $this->template->build(
            'no-reply@test.local',
            $this->createWithdraw('100.00'),
            $this->createPix('recipient@bank.com'),
        );

        $toAddresses = $email->getTo();
        $this->assertCount(1, $toAddresses);
        $this->assertSame('recipient@bank.com', $toAddresses[0]->getAddress());
    }

    #[Test]
    public function buildSetsCorrectSubject(): void
    {
        $email = $this->template->build(
            'no-reply@test.local',
            $this->createWithdraw('100.00'),
            $this->createPix('user@example.com'),
        );

        $this->assertSame('PIX Withdrawal Completed', $email->getSubject());
    }

    #[Test]
    public function buildHtmlContainsDateTime(): void
    {
        $withdraw = $this->createWithdraw('250.00');
        $expectedDate = $withdraw->createdAt()->format('Y-m-d H:i:s');

        $email = $this->template->build(
            'no-reply@test.local',
            $withdraw,
            $this->createPix('user@example.com'),
        );

        $this->assertStringContainsString($expectedDate, $email->getHtmlBody());
    }

    #[Test]
    public function buildHtmlContainsAmount(): void
    {
        $email = $this->template->build(
            'no-reply@test.local',
            $this->createWithdraw('1500.99'),
            $this->createPix('user@example.com'),
        );

        $this->assertStringContainsString('R$ 1500.99', $email->getHtmlBody());
    }

    #[Test]
    public function buildHtmlContainsPixType(): void
    {
        $email = $this->template->build(
            'no-reply@test.local',
            $this->createWithdraw('100.00'),
            $this->createPix('user@example.com'),
        );

        $this->assertStringContainsString('email', $email->getHtmlBody());
    }

    #[Test]
    public function buildHtmlContainsPixKey(): void
    {
        $email = $this->template->build(
            'no-reply@test.local',
            $this->createWithdraw('100.00'),
            $this->createPix('recipient@bank.com'),
        );

        $this->assertStringContainsString('recipient@bank.com', $email->getHtmlBody());
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
