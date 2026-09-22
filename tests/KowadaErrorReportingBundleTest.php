<?php

namespace Kowada\ErrorReportingBundle\Tests;

use Kowada\ErrorReportingBundle\KowadaErrorReportingBundle;
use PHPUnit\Framework\TestCase;

class KowadaErrorReportingBundleTest extends TestCase {

    public function testGetPathReturnsThePackageRootNotTheSrcDirectory(): void {
        $bundle = new KowadaErrorReportingBundle();

        $path = $bundle->getPath();

        $this->assertDirectoryExists($path . '/templates');
        $this->assertDirectoryExists($path . '/src');
        $this->assertFileDoesNotExist($path . '/KowadaErrorReportingBundle.php');
    }

}
