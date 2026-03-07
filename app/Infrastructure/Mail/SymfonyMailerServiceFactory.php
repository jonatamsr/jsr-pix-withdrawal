<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class SymfonyMailerServiceFactory
{
    private const DEFAULT_HOST = 'mailhog';

    private const DEFAULT_PORT = 1025;

    private const DEFAULT_FROM = 'no-reply@jsr-pix-withdrawal.local';

    public function __invoke(ContainerInterface $container): SymfonyMailerService
    {
        $config = $container->get(ConfigInterface::class);

        $host = $config->get('mail.host', self::DEFAULT_HOST);
        $port = (int) $config->get('mail.port', self::DEFAULT_PORT);
        $from = $config->get('mail.from', self::DEFAULT_FROM);

        $dsn = sprintf('smtp://%s:%d', $host, $port);
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        return new SymfonyMailerService($mailer, $from);
    }
}
