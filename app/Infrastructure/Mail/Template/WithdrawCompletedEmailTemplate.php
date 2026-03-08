<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Template;

use App\Domain\Entity\AccountWithdraw;
use App\Domain\Entity\AccountWithdrawPix;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Mime\Email;

class WithdrawCompletedEmailTemplate
{
    private const DEFAULT_TEMPLATE_PATH = __DIR__ . '/html/withdraw-completed.html';

    public function __construct(
        private readonly string $templatePath = self::DEFAULT_TEMPLATE_PATH,
    ) {
    }

    public function build(string $from, AccountWithdraw $withdraw, AccountWithdrawPix $pix, DateTimeImmutable $processedAt): Email
    {
        $dateTime = $processedAt->format('Y-m-d H:i:s');
        $amount = $withdraw->amount()->toDecimal();
        $pixType = $pix->pixKey()->type()->value;
        $pixKey = $pix->pixKey()->key();

        $html = $this->loadTemplate([
            '{{ dateTime }}' => $dateTime,
            '{{ amount }}' => $amount,
            '{{ pixType }}' => $pixType,
            '{{ pixKey }}' => $pixKey,
        ]);

        return (new Email())
            ->from($from)
            ->to($pixKey)
            ->subject('PIX Withdrawal Completed')
            ->html($html);
    }

    /**
     * @param array<string, string> $placeholders
     */
    private function loadTemplate(array $placeholders): string
    {
        $path = $this->templatePath;

        if (! file_exists($path)) {
            throw new RuntimeException("Email template not found: {$path}");
        }

        $html = file_get_contents($path);

        return str_replace(array_keys($placeholders), array_values($placeholders), $html);
    }
}
