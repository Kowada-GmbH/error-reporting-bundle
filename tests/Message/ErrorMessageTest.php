<?php

namespace Kowada\ErrorReportingBundle\Tests\Message;

use DateTimeImmutable;
use Kowada\ErrorReportingBundle\Message\ErrorMessage;
use PHPUnit\Framework\TestCase;

class ErrorMessageTest extends TestCase {

    public function testGetters(): void {
        $occurredAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $message = new ErrorMessage('error', 'Something failed', '/path/to/file.php', 42, 'Exception message', 'trace...', $occurredAt);

        $this->assertSame('error', $message->getLevel());
        $this->assertSame('Something failed', $message->getMessage());
        $this->assertSame('/path/to/file.php', $message->getExceptionFile());
        $this->assertSame(42, $message->getExceptionLine());
        $this->assertSame('Exception message', $message->getExceptionMessage());
        $this->assertSame('trace...', $message->getExceptionTrace());
        $this->assertSame($occurredAt, $message->getOccurredAt());
    }

}
