<?php

namespace Kowada\ErrorReportingBundle\Tests\Monolog;

use DateTimeImmutable;
use InvalidArgumentException;
use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use Kowada\ErrorReportingBundle\Monolog\ErrorHandler;
use Kowada\ErrorReportingBundle\Monolog\ErrorLogSpy;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

require_once __DIR__ . '/ErrorLogStub.php';

/**
 * Tests that {@see ErrorHandler} reports the right exceptions, ignores the rest, and never lets a reporting
 * failure escape write() or trigger a repeat report about itself.
 */
class ErrorHandlerTest extends TestCase {

    protected function setUp(): void {
        ErrorLogSpy::reset();
    }

    private function createRecord(?Throwable $exception): LogRecord {
        return new LogRecord(
            new DateTimeImmutable(),
            'app',
            Level::Error,
            'Something failed',
            $exception !== null ? ['exception' => $exception] : []
        );
    }

    public function testSkipsRecordsWithoutException(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        (new ErrorHandler($bus))->handle($this->createRecord(null));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function clientErrorStatusCodes(): iterable {
        foreach ([400, 401, 402, 403, 404, 405, 409, 410, 422, 429, 499] as $statusCode) {
            yield (string) $statusCode => [$statusCode];
        }
    }

    #[DataProvider('clientErrorStatusCodes')]
    public function testIgnoresClientErrorHttpExceptions(int $statusCode): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        (new ErrorHandler($bus))->handle($this->createRecord(new HttpException($statusCode)));
    }

    public function testDispatchesErrorMessageForServerErrorHttpExceptions(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ErrorMessage::class))
            ->willReturn(new Envelope(new stdClass()));

        (new ErrorHandler($bus))->handle($this->createRecord(new HttpException(500)));
    }

    public function testDispatchesErrorMessageForRealExceptions(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ErrorMessage::class))
            ->willReturn(new Envelope(new stdClass()));

        (new ErrorHandler($bus))->handle($this->createRecord(new RuntimeException('boom')));

        $this->assertSame([], ErrorLogSpy::$messages);
    }

    public function testSwallowsDispatchFailuresInsteadOfThrowing(): void {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException('transport down'));

        (new ErrorHandler($bus))->handle($this->createRecord(new RuntimeException('boom')));

        $this->assertCount(1, ErrorLogSpy::$messages);
        $this->assertStringContainsString('transport down', ErrorLogSpy::$messages[0]);
        $this->assertStringContainsString('boom', ErrorLogSpy::$messages[0]);
    }

    public function testPassesExceptionClassOn(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (ErrorMessage $message): bool => $message->getExceptionClass() === RuntimeException::class))
            ->willReturn(new Envelope(new stdClass()));

        (new ErrorHandler($bus))->handle($this->createRecord(new RuntimeException('boom')));
    }

    public function testDoesNotRedispatchWhenMailerOfOwnReportCannotBeCreated(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        (new ErrorHandler($bus))->handle($this->createMessengerFailureRecord(ErrorMessage::class, new InvalidArgumentException('The mailer DSN is invalid.')));

        $this->assertCount(1, ErrorLogSpy::$messages);
        $this->assertStringContainsString('The mailer DSN is invalid.', ErrorLogSpy::$messages[0]);
    }

    public function testReportsMessengerFailuresOfOtherMessages(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ErrorMessage::class))
            ->willReturn(new Envelope(new stdClass()));

        (new ErrorHandler($bus))->handle($this->createMessengerFailureRecord(stdClass::class, new RuntimeException('handler failed')));
    }

    /**
     * Builds the record Messenger logs once a message has used up its retries, as seen in production when the mailer DSN was invalid.
     *
     * @param class-string $messageClass The class of the message that failed
     * @param Throwable $exception The exception Messenger logs with it
     */
    private function createMessengerFailureRecord(string $messageClass, Throwable $exception): LogRecord {
        return new LogRecord(
            new DateTimeImmutable(),
            'messenger',
            Level::Critical,
            'Error thrown while handling message {class}. Removing from transport after {retryCount} retries. Error: "{error}"',
            ['class' => $messageClass, 'message_id' => 4, 'retryCount' => 3, 'error' => $exception->getMessage(), 'exception' => $exception]
        );
    }

    public function testDoesNotRedispatchWhenOwnReportFailedToDeliver(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $envelope = new Envelope(new ErrorMessage('error', 'Something failed', '/path/file.php', 10, 'Boom', 'trace', new DateTimeImmutable()));
        $handlerFailedException = new HandlerFailedException($envelope, ['handler' => new RuntimeException('mailer down')]);

        (new ErrorHandler($bus))->handle($this->createRecord($handlerFailedException));

        $this->assertCount(1, ErrorLogSpy::$messages);
        $this->assertStringContainsString('mailer down', ErrorLogSpy::$messages[0]);
    }

}
