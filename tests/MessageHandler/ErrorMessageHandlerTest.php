<?php

namespace Kowada\ErrorReportingBundle\Tests\MessageHandler;

use DateTimeImmutable;
use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use Kowada\ErrorReportingBundle\MessageHandler\ErrorMessageHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\RawMessage;

class ErrorMessageHandlerTest extends TestCase {

    private function createMessage(): ErrorMessage {
        return new ErrorMessage('error', 'Something failed', '/path/file.php', 10, 'Boom', 'trace', new DateTimeImmutable());
    }

    public function testThrowsWhenReceiverNotConfigured(): void {
        $handler = new ErrorMessageHandler($this->createStub(MailerInterface::class));

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler($this->createMessage());
    }

    public function testThrowsWhenSenderAddressNotConfigured(): void {
        $handler = new ErrorMessageHandler($this->createStub(MailerInterface::class), errorReportingReceiver: 'ops@example.com');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler($this->createMessage());
    }

    public function testSendsEmailWhenConfigured(): void {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->isInstanceOf(RawMessage::class));

        $handler = new ErrorMessageHandler(
            $mailer,
            errorReportingReceiver: 'ops@example.com',
            errorReportingSenderAddress: 'noreply@example.com',
            errorReportingSenderName: 'App',
            errorReportingAppName: 'MyApp',
            environment: 'prod'
        );

        $handler($this->createMessage());
    }

}
