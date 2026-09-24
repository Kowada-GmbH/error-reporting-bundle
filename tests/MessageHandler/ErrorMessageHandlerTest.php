<?php

namespace Kowada\ErrorReportingBundle\Tests\MessageHandler;

use DateTimeImmutable;
use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use Kowada\ErrorReportingBundle\MessageHandler\ErrorMessageHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\RawMessage;

class ErrorMessageHandlerTest extends TestCase {

    private function createMessage(string $exceptionMessage = 'Boom', string $exceptionClass = 'App\\Exception\\SyncFailed'): ErrorMessage {
        return new ErrorMessage('error', 'Something failed', '/path/file.php', 10, $exceptionMessage, 'trace', new DateTimeImmutable(), $exceptionClass);
    }

    private function createHandler(MailerInterface $mailer): ErrorMessageHandler {
        return new ErrorMessageHandler(
            $mailer,
            errorReportingReceiver: 'ops@example.com',
            errorReportingSenderAddress: 'noreply@example.com',
            errorReportingSenderName: 'App',
            errorReportingAppName: 'MyApp',
            environment: 'prod'
        );
    }

    /**
     * Handles $message and returns the e-mail the handler passed to the mailer.
     */
    private function sendAndCapture(ErrorMessage $message): TemplatedEmail {
        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->willReturnCallback(static function (RawMessage $email) use (&$sent): void {
            $sent[] = $email;
        });

        $this->createHandler($mailer)($message);

        $this->assertInstanceOf(TemplatedEmail::class, $sent[0] ?? null);

        return $sent[0];
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

    public function testSubjectNamesTheException(): void {
        $email = $this->sendAndCapture($this->createMessage());

        $this->assertSame('ERROR MyApp(prod): SyncFailed: Boom', $email->getSubject());
    }

    public function testSubjectPutsMultiLineExceptionMessagesOnOneLineAndShortensThem(): void {
        $email = $this->sendAndCapture($this->createMessage("SQLSTATE[08006]:\n    connection refused " . str_repeat('x', 200)));

        $subject = (string) $email->getSubject();
        $this->assertStringStartsWith('ERROR MyApp(prod): SyncFailed: SQLSTATE[08006]: connection refused x', $subject);
        $this->assertStringEndsWith('…', $subject);
        $this->assertSame(150, mb_strlen(substr($subject, strlen('ERROR MyApp(prod): '))));
    }

    public function testSubjectFallsBackToLogMessageWithoutExceptionDetails(): void {
        $email = $this->sendAndCapture($this->createMessage('', ''));

        $this->assertSame('ERROR MyApp(prod): Something failed', $email->getSubject());
    }

    public function testPassesLogMessageAndExceptionSeparatelyToTheTemplates(): void {
        $context = $this->sendAndCapture($this->createMessage())->getContext();

        $this->assertSame('MyApp', $context['appName']);
        $this->assertSame('prod', $context['environment']);
        $this->assertSame('Something failed', $context['message']);
        $this->assertSame('App\\Exception\\SyncFailed', $context['exceptionClass']);
        $this->assertSame('Boom', $context['exceptionMessage']);
    }

}
