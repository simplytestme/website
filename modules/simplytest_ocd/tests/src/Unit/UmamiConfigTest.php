<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\Umami;

final class UmamiConfigTest extends OneClickDemoConfigTestBase {

  protected static $pluginId = 'oneclickdemo_umami';
  protected static $pluginClass = Umami::class;

  protected function getExpectedConfig(): array {
    return [
      'php' => [
        'image' => 'tugboatqa/php:7.3-apache',
        'default' => TRUE,
        'depends' => 'mysql',
        'commands' => [
          'build' => [
            'composer self-update',
            'rm -rf "${DOCROOT}"',
            'composer -n create-project drupal/recommended-project:^9.0 stm --no-install',
            'cd stm && composer require --no-update drupal/core-recommended:^9.0',
            'cd stm && composer require --no-update drupal/core-composer-scaffold:^9.0',
            'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default',
            'cd stm && composer require --no-update drush/drush:^10.0',
            'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'cd stm && composer update --no-ansi --no-dev',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'php -d memory_limit=-1 stm/vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
            'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
            'echo "SIMPLYEST_STAGE_FINALIZE"',
          ],
        ],
      ],
      'mysql' => [
        'image' => 'tugboatqa/mysql:5',
      ],
    ];
  }

}
