<?php

namespace Kowada\ErrorReportingBundle\Message;

use DateTimeInterface;

/**
 * Messenger message carrying a logged error's details to {@see \Kowada\ErrorReportingBundle\MessageHandler\ErrorMessageHandler} for e-mail reporting.
 */
readonly class ErrorMessage {

    public function __construct(
        private string $level,
        private string $message,
        private string $exceptionFile,
        private int $exceptionLine,
        private string $exceptionMessage,
        private string $exceptionTrace,
        private DateTimeInterface $occurredAt,
        private ?string $exceptionClass = null
    ) {}

    /**
     * @return string The Monolog level name (e.g. "ERROR", "CRITICAL").
     */
    public function getLevel(): string {
        return $this->level;
    }

    /**
     * @return string The log message.
     */
    public function getMessage(): string {
        return $this->message;
    }

    /**
     * @return string The absolute path of the file where the exception was thrown.
     */
    public function getExceptionFile(): string {
        return $this->exceptionFile;
    }

    /**
     * @return int The line number where the exception was thrown.
     */
    public function getExceptionLine(): int {
        return $this->exceptionLine;
    }

    /**
     * @return string The exception's own message.
     */
    public function getExceptionMessage(): string {
        return $this->exceptionMessage;
    }

    /**
     * @return string The exception's stack trace, as produced by `Throwable::getTraceAsString()`.
     */
    public function getExceptionTrace(): string {
        return $this->exceptionTrace;
    }

    /**
     * @return DateTimeInterface The moment the error was logged.
     */
    public function getOccurredAt(): DateTimeInterface {
        return $this->occurredAt;
    }

    /**
     * @return string The exception's fully qualified class name, or an empty string for messages queued by versions before 1.0.9.
     */
    public function getExceptionClass(): string {
        return $this->exceptionClass ?? '';
    }

}
