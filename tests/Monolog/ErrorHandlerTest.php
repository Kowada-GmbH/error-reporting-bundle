<?php

namespace Kowada\ErrorReportingBundle\Tests\Monolog;

use DateTimeImmutable;
use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use Kowada\ErrorReportingBundle\Monolog\ErrorHandler;
use Kowada\ErrorReportingBundle\Monolog\ErrorLogSpy;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

    public function testIgnoresNotFoundExceptions(): void {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        (new ErrorHandler($bus))->handle($this->createRecord(new NotFoundHttpException()));
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
