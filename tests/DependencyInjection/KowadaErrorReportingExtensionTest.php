<?php

namespace Kowada\ErrorReportingBundle\Tests\DependencyInjection;

use Kowada\ErrorReportingBundle\DependencyInjection\KowadaErrorReportingExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class KowadaErrorReportingExtensionTest extends TestCase {

    public function testAliasMatchesConfigurationRootName(): void {
        $extension = new KowadaErrorReportingExtension();

        $this->assertSame('kowada_error_reporting', $extension->getAlias());
    }

    public function testLoadCompilesContainerWithoutErrors(): void {
        $this->expectNotToPerformAssertions();

        $container = new ContainerBuilder();
        $extension = new KowadaErrorReportingExtension();

        $extension->load([], $container);

        $container->compile();
    }

}
