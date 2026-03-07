<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Infrastructure\Mail;

use App\Infrastructure\Mail\SymfonyMailerService;
use App\Infrastructure\Mail\SymfonyMailerServiceFactory;
use Hyperf\Contract\ConfigInterface;
use HyperfTest\Support\UsesMockery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 */
class SymfonyMailerServiceFactoryTest extends TestCase
{
    use UsesMockery;

    private ConfigInterface|Mockery\MockInterface $config;

    private ContainerInterface|Mockery\MockInterface $container;

    private SymfonyMailerServiceFactory $factory;

    protected function setUp(): void
    {
        $this->config = Mockery::mock(ConfigInterface::class);

        $this->container = Mockery::mock(ContainerInterface::class);
        $this->container
            ->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($this->config);

        $this->factory = new SymfonyMailerServiceFactory();
    }

    #[Test]
    public function invokeReturnsSymfonyMailerServiceWithConfigValues(): void
    {
        $this->config->shouldReceive('get')->with('mail.host', 'mailhog')->andReturn('smtp.example.com');
        $this->config->shouldReceive('get')->with('mail.port', 1025)->andReturn(587);
        $this->config->shouldReceive('get')->with('mail.from', 'no-reply@jsr-pix-withdrawal.local')->andReturn('sender@example.com');

        $result = ($this->factory)($this->container);

        $this->assertInstanceOf(SymfonyMailerService::class, $result);
    }

    #[Test]
    public function invokeUsesDefaultConfigValues(): void
    {
        $this->config->shouldReceive('get')->with('mail.host', 'mailhog')->andReturn('mailhog');
        $this->config->shouldReceive('get')->with('mail.port', 1025)->andReturn(1025);
        $this->config->shouldReceive('get')->with('mail.from', 'no-reply@jsr-pix-withdrawal.local')
            ->andReturn('no-reply@jsr-pix-withdrawal.local');

        $result = ($this->factory)($this->container);

        $this->assertInstanceOf(SymfonyMailerService::class, $result);
    }
}
