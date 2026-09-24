# Kowada Error Reporting Bundle

![CI](https://github.com/Kowada-GmbH/error-reporting-bundle/workflows/CI/badge.svg)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-%5E7.4-000000?logo=symfony&logoColor=white)

Shared Symfony bundle that automatically e-mails logged errors to a configurable address.

The bundle is maintained here as a versioned Composer dependency and pulled into individual Symfony projects via `composer update kowada-gmbh/error-reporting-bundle`.

## Installation

The package is public on [Packagist](https://packagist.org/packages/kowada-gmbh/error-reporting-bundle) (the source itself stays proprietary, only the distribution is public) and can be required as a regular dependency:

```console
composer require kowada-gmbh/error-reporting-bundle ^1.0
```

## Features

All classes under `Kowada\ErrorReportingBundle\` are automatically registered as services via autowiring/autoconfiguration (see [config/services.yaml](config/services.yaml)).

Errors that get logged (except HTTP client errors, i.e. any 4xx status) are automatically e-mailed to a configurable address.

- [`Monolog\ErrorHandler`](src/Monolog/ErrorHandler.php): Monolog handler that dispatches errors as an [`Message\ErrorMessage`](src/Message/ErrorMessage.php) over the Messenger bus.
- [`MessageHandler\ErrorMessageHandler`](src/MessageHandler/ErrorMessageHandler.php): sends the e-mail, including the stack trace as an attachment, via `@KowadaErrorReporting/emails/error.{html,txt}.twig` (needs `symfony/twig-bundle` active - without it, `TemplatedEmail` silently sends with an empty body instead of failing loudly). The subject names the exception, e.g. `ERROR my-app(prod): RuntimeException: Boom`, and the body lists the log message and the exception (class, message, file and line) under separate headings.

If sending the e-mail itself fails (misconfiguration, mailer outage, unreachable Messenger transport), `ErrorHandler` catches that instead of letting the exception propagate: the original error is therefore never hidden (it still reaches the other Monolog handlers as usual, e.g. the log file), and the reporting failure is additionally recorded via PHP's `error_log()`. If an asynchronous Messenger worker ultimately fails to deliver an `ErrorMessage` (after exhausting retries), this is detected instead of dispatching another `ErrorMessage` about it — preventing a loop of error reports about failed error reports. Detection relies on the message class Messenger adds to its log context, so it also covers failures before the handler runs, like a mailer that can't be created from an invalid `MAILER_DSN`.

**Configuration in the consuming project:**

1. Bundle configuration in `config/packages/kowada_error_reporting.yaml`:
   ```yaml
   kowada_error_reporting:
       receiver: 'errors@example.com'
       sender_address: 'noreply@example.com'
       sender_name: 'Example Admin'
       app_name: 'example.com'
   ```
   If `receiver` or `sender_address` is missing, the handler throws an `UnrecoverableMessageHandlingException` on every error. This does not abort the request/command — `ErrorHandler` catches it (see above) and writes a notice via `error_log()` instead. Needless to say, you can also use environment variables for configuration instead of baking the values into the app.

2. Register the handler in `config/packages/monolog.yaml` — recommended in front of Monolog's own `deduplication` handler, so a recurring error (a cron loop, a broken high-traffic page) doesn't trigger a new e-mail on every single occurrence:
   ```yaml
   monolog:
       handlers:
           kowada_error_mail:
               type: service
               id: Kowada\ErrorReportingBundle\Monolog\ErrorHandler
           kowada_error_mail_dedup:
               type: deduplication
               handler: kowada_error_mail
               time: 1800
   ```
   `kowada_error_mail_dedup` is the handler actually active in the stack; MonologBundle recognizes `kowada_error_mail` as referenced by it and does not additionally register it on its own — so nothing needs to be configured twice here. `time` (in seconds) sets how long an *identical* error (same level + same message text) is suppressed after the first delivery; the first occurrence of an error is never delayed by this. If plain registration without deduplication is enough, the `kowada_error_mail` block alone suffices.

3. Recommended: route `ErrorMessage` to an asynchronous Messenger transport so sending the e-mail doesn't block the request:
   ```yaml
   framework:
       messenger:
           transports:
               async: '%env(MESSENGER_TRANSPORT_DSN)%'
           routing:
               Kowada\ErrorReportingBundle\Message\ErrorMessage: async
   ```
   Without routing, the e-mail is sent synchronously within the current request.

4. A working `symfony/mailer` transport (`MAILER_DSN`) is assumed.

## Development

```console
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
