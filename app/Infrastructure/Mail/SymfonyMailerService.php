<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use App\Infrastructure\Mail\Template\WithdrawCompletedEmailTemplate;
use DateTimeImmutable;
use Symfony\Component\Mailer\MailerInterface;

class SymfonyMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $from,
        private readonly WithdrawCompletedEmailTemplate $withdrawCompletedTemplate = new WithdrawCompletedEmailTemplate(),
    ) {
    }

    public function sendWithdrawCompleted(
        AccountWithdraw $withdraw,
        AccountWithdrawPix $pix,
        DateTimeImmutable $processedAt,
    ): void {
        $email = $this->withdrawCompletedTemplate->build($this->from, $withdraw, $pix, $processedAt);

        $this->mailer->send($email);
    }
}
