<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Entity;

use App\Domain\Entity\AccountWithdrawPix;
use App\Domain\ValueObject\PixKey;
use App\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class AccountWithdrawPixTest extends TestCase
{
    #[Test]
    public function createWithValidData(): void
    {
        $withdrawId = Uuid::generate();
        $pixKey = PixKey::create('email', 'fulano@email.com');

        $withdrawPix = AccountWithdrawPix::create($withdrawId, $pixKey);

        $this->assertSame($withdrawId, $withdrawPix->accountWithdrawId());
        $this->assertSame($pixKey, $withdrawPix->pixKey());
    }

    #[Test]
    public function pixKeyIsAccessible(): void
    {
        $pixKey = PixKey::create('email', 'test@example.com');
        $withdrawPix = AccountWithdrawPix::create(Uuid::generate(), $pixKey);

        $this->assertSame('test@example.com', $withdrawPix->pixKey()->key());
        $this->assertSame('email', $withdrawPix->pixKey()->type()->value);
    }
}
