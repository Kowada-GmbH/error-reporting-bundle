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
 */
#[AsMessageHandler]
readonly class ErrorMessageHandler {

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
            $message->getExceptionMessage()
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
                'level' => $message->getLevel(),
                'message' => $message->getMessage(),
                'exceptionFile' => $message->getExceptionFile(),
                'exceptionLine' => $message->getExceptionLine(),
                'exceptionMessage' => $message->getExceptionMessage(),
                'occurredAt' => $message->getOccurredAt(),
            ]);

        $this->mailer->send($email);
    }

}
