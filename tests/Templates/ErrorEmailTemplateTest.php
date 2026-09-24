<?php

namespace Kowada\ErrorReportingBundle\Tests\Templates;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renders the error e-mail templates to check that the log message and the exception are labeled separately.
 */
class ErrorEmailTemplateTest extends TestCase {

    /**
     * @return array<string, mixed> The context {@see \Kowada\ErrorReportingBundle\MessageHandler\ErrorMessageHandler} passes to the templates
     */
    private function createContext(): array {
        return [
            'subject' => 'ERROR MyApp(prod): SyncFailed: Boom',
            'appName' => 'MyApp',
            'environment' => 'prod',
            'level' => 'error',
            'message' => 'Could not sync the shopping list',
            'exceptionClass' => 'App\\Exception\\SyncFailed',
            'exceptionFile' => '/app/src/Sync.php',
            'exceptionLine' => 42,
            'exceptionMessage' => 'Boom <b>',
            'occurredAt' => new DateTimeImmutable('2026-09-24 21:45:46', new DateTimeZone('UTC')),
        ];
    }

    private function render(string $template): string {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 2) . '/templates', 'KowadaErrorReporting');
        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'name']);

        return $twig->render('@KowadaErrorReporting/emails/' . $template, $this->createContext());
    }

    public function testTextTemplateLabelsLogMessageAndException(): void {
        $text = $this->render('error.txt.twig');

        $this->assertStringContainsString('ERROR in MyApp (prod) at 2026-09-24', $text);
        $this->assertStringContainsString("Log message:\nCould not sync the shopping list\n", $text);
        $this->assertStringContainsString("Exception (App\\Exception\\SyncFailed):\nBoom <b>\nThrown in /app/src/Sync.php:42\n", $text);
    }

    public function testHtmlTemplateLabelsLogMessageAndExceptionAndEscapesThem(): void {
        $html = $this->render('error.html.twig');

        $this->assertStringContainsString('ERROR in MyApp (prod)', $html);
        $this->assertStringContainsString('>Log message</h3>', $html);
        $this->assertStringContainsString('>Could not sync the shopping list</p>', $html);
        $this->assertStringContainsString('App\\Exception\\SyncFailed</code>', $html);
        $this->assertStringContainsString('>Boom &lt;b&gt;</p>', $html);
        $this->assertStringContainsString('/app/src/Sync.php:42', $html);
    }

}
