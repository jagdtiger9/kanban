<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Infrastructure\Mailer;

use App\User\Infrastructure\Mailer\MailerConfirmationEmailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class MailerConfirmationEmailSenderTest extends TestCase
{
    public function testSendsConfirmationEmailWithFrontendLink(): void
    {
        $captured = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(function (RawMessage $message) use (&$captured): void {
                $captured = $message;
            });

        $sender = new MailerConfirmationEmailSender($mailer, 'https://canban.local');
        $sender->send('alice@example.com', 'Alice', 'deadbeef');

        self::assertInstanceOf(Email::class, $captured);
        self::assertSame('alice@example.com', $captured->getTo()[0]->getAddress());
        self::assertSame('Confirm your Canban account', $captured->getSubject());
        self::assertStringContainsString('Hello Alice', $captured->getTextBody());
        self::assertStringContainsString('https://canban.local/confirm/deadbeef', $captured->getTextBody());
    }

    public function testStripsTrailingSlashFromFrontendUrl(): void
    {
        $captured = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(function (RawMessage $message) use (&$captured): void {
                $captured = $message;
            });

        $sender = new MailerConfirmationEmailSender($mailer, 'https://canban.local/');
        $sender->send('alice@example.com', 'Alice', 'deadbeef');

        self::assertInstanceOf(Email::class, $captured);
        self::assertStringContainsString('https://canban.local/confirm/deadbeef', $captured->getTextBody());
        self::assertStringNotContainsString('//confirm', $captured->getTextBody());
    }
}
