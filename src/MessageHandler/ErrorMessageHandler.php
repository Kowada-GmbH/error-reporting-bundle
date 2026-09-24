<?php

namespace Kowada\ErrorReportingBundle\MessageHandler;

use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends an {@see ErrorMessage} as an e-mail to the configured `kowada_error_reporting.receiver`, with the stack trace attached as a text file.
 *
 * The subject names the exception ("RuntimeException: Boom"), the body lists the log message and the exception separately.
 */
#[AsMessageHandler]
readonly class ErrorMessageHandler {

    private const int SUBJECT_SUMMARY_LENGTH = 150;

    /**
     * @param string|null $errorReportingReceiver Bound to the `kowada_error_reporting.receiver` parameter.
     * @param string|null $errorReportingSenderAddress Bound to the `kowada_error_reporting.sender_address` parameter.
     * @param string|null $errorReportingSenderName Bound to the `kowada_error_reporting.sender_name` parameter.
     * @param string|null $errorReportingAppName Bound to the `kowada_error_reporting.app_name` parameter.
     */
    public function __construct(
        private MailerInterface $mailer,
        private ?string $errorReportingReceiver = null,
        private ?string $errorReportingSenderAddress = null,
        private ?string $errorReportingSenderName = null,
        private ?string $errorReportingAppName = null,
        private string $environment = 'prod'
    ) {}

    /**
     * @throws UnrecoverableMessageHandlingException If the `receiver` or `sender_address` config option is not set - retrying would not help, so Messenger should not redeliver.
     */
    public function __invoke(ErrorMessage $message): void {
        if ($this->errorReportingReceiver === null || $this->errorReportingReceiver === '') {
            throw new UnrecoverableMessageHandlingException('The "kowada_error_reporting.receiver" option is not configured.');
        }

        if ($this->errorReportingSenderAddress === null || $this->errorReportingSenderAddress === '') {
            throw new UnrecoverableMessageHandlingException('The "kowada_error_reporting.sender_address" option is not configured.');
        }

        $subject = sprintf(
            '%s %s(%s): %s',
            strtoupper($message->getLevel()),
            $this->errorReportingAppName ?? '',
            $this->environment,
            $this->summarize($message)
        );

        $attachment = new DataPart(
            $message->getExceptionTrace() !== '' ? $message->getExceptionTrace() : 'No stack trace available.',
            sprintf('%s_%s.txt', $message->getOccurredAt()->format('Y-m-d_H-i-s'), $message->getLevel()),
            'text/plain'
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->errorReportingSenderAddress, $this->errorReportingSenderName ?? ''))
            ->to($this->errorReportingReceiver)
            ->subject($subject)
            ->htmlTemplate('@KowadaErrorReporting/emails/error.html.twig')
            ->textTemplate('@KowadaErrorReporting/emails/error.txt.twig')
            ->addPart($attachment)
            ->context([
                'subject' => $subject,
                'appName' => $this->errorReportingAppName,
                'environment' => $this->environment,
                'level' => $message->getLevel(),
                'message' => $message->getMessage(),
                'exceptionClass' => $message->getExceptionClass(),
                'exceptionFile' => $message->getExceptionFile(),
                'exceptionLine' => $message->getExceptionLine(),
                'exceptionMessage' => $message->getExceptionMessage(),
                'occurredAt' => $message->getOccurredAt(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * @return string The exception as a single line for the subject, like "RuntimeException: Boom", shortened to {@see self::SUBJECT_SUMMARY_LENGTH} characters. Falls back to the log message when the exception has neither class nor message.
     */
    private function summarize(ErrorMessage $message): string {
        $summary = $this->toSingleLine($message->getExceptionMessage());
        $className = $message->getExceptionClass();

        if ($className !== '') {
            $shortClassName = substr((string) strrchr('\\' . $className, '\\'), 1);
            $summary = $summary !== '' ? sprintf('%s: %s', $shortClassName, $summary) : $shortClassName;
        }

        if ($summary === '') {
            $summary = $this->toSingleLine($message->getMessage());
        }

        if (mb_strlen($summary) > self::SUBJECT_SUMMARY_LENGTH) {
            $summary = rtrim(mb_substr($summary, 0, self::SUBJECT_SUMMARY_LENGTH - 1)) . '…';
        }

        return $summary;
    }

    /**
     * @return string $text with every run of whitespace, including line breaks, collapsed into a single space.
     */
    private function toSingleLine(string $text): string {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

}
