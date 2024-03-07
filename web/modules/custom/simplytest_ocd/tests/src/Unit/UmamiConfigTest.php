<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\Umami;

final class UmamiConfigTest extends OneClickDemoConfigTestBase {

  protected static $pluginId = 'oneclickdemo_umami';
  protected static $pluginClass = Umami::class;

  protected function getExpectedConfig(): array {
    return [
      'php' => [
        'image' => 'tugboatqa/php:8.1-apache',
        'default' => TRUE,
        'depends' => 'mysql',
        'commands' => [
          'build' => [
            'composer self-update',
            'docker-php-ext-install opcache',
            'a2enmod headers rewrite',
            'rm -rf "${DOCROOT}"',
            'composer -n create-project drupal/recommended-project stm --no-install',
            'cd stm && composer require --no-update drush/drush',
            'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'cd ${DOCROOT} && php -d memory_limit=-1 ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
            'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
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
