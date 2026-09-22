<?php

namespace Kowada\ErrorReportingBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The bundle class registering Kowada's automatic error-reporting-by-e-mail functionality with the kernel.
 */
class KowadaErrorReportingBundle extends Bundle {

    /**
     * @return string The bundle's root directory (one level up from `src/`), used to locate `config/` and `templates/`.
     */
    public function getPath(): string {
        return \dirname(__DIR__);
    }

}
