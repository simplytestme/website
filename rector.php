<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/drush',
        __DIR__ . '/web/modules/custom',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(0);
