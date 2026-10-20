<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

final class SmtpCompositionTest extends TestCase
{
    #[DataProvider('branches')]
    public function testProductionMailerPreservesEncryptionAndCredentials(string $encryption, string $username): void
    {
        $container = ProductionContainerFixture::configured([
            'notification' => [
                'email' => [
                    'driver' => 'smtp',
                    'smtp' => [
                        'host' => '127.0.0.1',
                        'port' => 2525,
                        'encryption' => $encryption,
                        'username' => $username,
                        'password' => $username === '' ? '' : 'fixture-password',
                        'timeout-seconds' => 1.5,
                    ],
                ],
            ],
        ]);
        $mailer    = $container->get(MailerInterface::class);
        self::assertInstanceOf(Mailer::class, $mailer);
        $property  = new ReflectionProperty(Mailer::class, 'transport');
        $transport = $property->getValue($mailer);
        self::assertInstanceOf(EsmtpTransport::class, $transport);
        self::assertSame($username, $transport->getUsername());
        self::assertSame($username === '' ? '' : 'fixture-password', $transport->getPassword());
        self::assertSame($encryption === 'starttls', $transport->isAutoTls());
        self::assertSame($encryption === 'starttls', $transport->isTlsRequired());
        $stream = $transport->getStream();
        self::assertInstanceOf(SocketStream::class, $stream);
        self::assertSame($encryption === 'smtps', $stream->isTLS());
        self::assertSame('127.0.0.1', $stream->getHost());
        self::assertSame(2525, $stream->getPort());
        self::assertSame(1.5, $stream->getTimeout());
    }

    /** @return iterable<string, array{string, string}> */
    public static function branches(): iterable
    {
        yield 'plain anonymous' => ['none', ''];
        yield 'plain authenticated' => ['none', 'fixture'];
        yield 'STARTTLS anonymous' => ['starttls', ''];
        yield 'STARTTLS authenticated' => ['starttls', 'fixture'];
        yield 'SMTPS anonymous' => ['smtps', ''];
        yield 'SMTPS authenticated' => ['smtps', 'fixture'];
    }
}
