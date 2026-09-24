<?php

namespace Kowada\ErrorReportingBundle\Monolog;

use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Monolog handler that reports logged exceptions by e-mail, dispatching an {@see ErrorMessage} through the Messenger bus.
 *
 * HTTP client errors (4xx) are ignored as noise. Failures to deliver the report itself are swallowed and written to
 * `error_log()` instead of being logged again, so a broken mailer/transport cannot trigger an infinite report loop.
 */
class ErrorHandler extends AbstractProcessingHandler {

    /**
     * @param Level $level The minimum log level this handler reports by e-mail.
     * @param bool $bubble Whether the record should also be passed to the next handler in the stack.
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
        Level $level = Level::Error,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void {
        $exception = $record->context['exception'] ?? null;

        if (!$exception instanceof Throwable) {
            return;
        }

        if ($this->isClientError($exception)) {
            return;
        }

        if ($this->isFailedDeliveryOfOwnReport($record, $exception)) {
            error_log(sprintf('Kowada ErrorReportingBundle: error-report email could not be delivered: %s', $exception->getMessage()));

            return;
        }

        try {
            $errorMessage = new ErrorMessage(
                $record->level->name,
                $record->message,
                $exception->getFile(),
                $exception->getLine(),
                $exception->getMessage(),
                $exception->getTraceAsString(),
                $record->datetime,
                $exception::class
            );

            $this->bus->dispatch($errorMessage);
        } catch (Throwable $reportingFailure) {
            error_log(sprintf(
                'Kowada ErrorReportingBundle: failed to report %s "%s" in %s:%d by email: %s',
                $record->level->name,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $reportingFailure->getMessage()
            ));
        }
    }

    /**
     * @return bool Whether $exception is an HTTP exception carrying a 4xx client error status code.
     */
    private function isClientError(Throwable $exception): bool {
        return $exception instanceof HttpExceptionInterface && $exception->getStatusCode() >= 400 && $exception->getStatusCode() < 500;
    }

    /**
     * Messenger names the failed message's class in the `class` context of its retry logs. That is the reliable signal,
     * because the logged exception is only wrapped in a {@see HandlerFailedException} when the handler itself threw;
     * a mailer that can't even be created (e.g. from an invalid DSN) fails before, with the bare exception.
     *
     * @return bool Whether $record is Messenger reporting that a previously dispatched {@see ErrorMessage} itself failed to be handled (e.g. the mailer is down).
     */
    private function isFailedDeliveryOfOwnReport(LogRecord $record, Throwable $exception): bool {
        if (($record->context['class'] ?? null) === ErrorMessage::class) {
            return true;
        }

        return $exception instanceof HandlerFailedException && $exception->getEnvelope()->getMessage() instanceof ErrorMessage;
    }

}
