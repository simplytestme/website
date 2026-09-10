<?php

declare(strict_types=1);

use DrupalRector\Set\DrupalSetProvider;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        // No `drush/`: everything under it is composer-installed contrib, and
        // our own Drush commands live in the modules.
        __DIR__ . '/web/modules/custom',
        __DIR__ . '/web/profiles/simplytest',
        __DIR__ . '/web/themes/simplytest_theme',
    ])
    // Reads the installed drupal/core and phpunit/phpunit versions and loads
    // only the sets that apply to them, so the rules track a dependency bump
    // instead of a hand-maintained version list. The Drupal group also brings
    // the bootstrap that turns off DeprecationHelper wrapping and teaches
    // Rector about .module, .install, .profile and .theme files.
    ->withSetProviders(DrupalSetProvider::class)
    ->withComposerBased(drupal: true, phpunit: true)
    // Annotations that carry no value (@group, @test). The ones that do
    // (@covers, @dataProvider, @depends) come from the composer-based set,
    // which knows which PHPUnit version added each attribute.
    ->withAttributesSets(phpunit: true)
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    // Drupal's coding standards want a `use` statement rather than a
    // fully-qualified name inline, which is how Rector writes attributes
    // otherwise. Doc blocks keep their fully-qualified type hints.
    ->withImportNames(importDocBlockNames: false, importShortClasses: false);
