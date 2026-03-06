<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

class SymfonyMailerServiceFactory
{
    public function __invoke(ContainerInterface $container): SymfonyMailerService
    {
        $config = $container->get(ConfigInterface::class);

        $host = $config->get('mail.host', 'mailhog');
        $port = (int) $config->get('mail.port', 1025);
        $from = $config->get('mail.from', 'no-reply@jsr-pix-withdrawal.local');

        $dsn = sprintf('smtp://%s:%d', $host, $port);
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        return new SymfonyMailerService($mailer, $from);
    }
}
