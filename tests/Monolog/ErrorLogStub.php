<?php

namespace Kowada\ErrorReportingBundle\Monolog;

/**
 * Test double for PHP's {@see \error_log()}, collecting calls in memory instead of writing them.
 *
 * Relies on PHP's namespace fallback resolution: the unqualified `error_log()` call in
 * {@see ErrorHandler} resolves to the `error_log()` function declared below in the same
 * namespace instead of the global built-in, once this file is loaded.
 */
final class ErrorLogSpy {

    /** @var list<string> */
    public static array $messages = [];

    /**
     * Clears all recorded calls. Must run before each test to avoid leaking state between tests.
     */
    public static function reset(): void {
        self::$messages = [];
    }

}

/**
 * @param string $message
 * @param int $message_type
 * @param string|null $destination
 * @param string|null $extra_headers
 * @return bool Always true, mirroring the built-in's success case.
 */
function error_log(string $message, int $message_type = 0, ?string $destination = null, ?string $extra_headers = null): bool {
    ErrorLogSpy::$messages[] = $message;

    return true;
}
