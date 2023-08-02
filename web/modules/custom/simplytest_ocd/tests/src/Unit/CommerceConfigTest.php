<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\Commerce;

final class CommerceConfigTest extends OneClickDemoConfigTestBase {

  protected static $pluginId = 'oneclickdemo_commerce';
  protected static $pluginClass = Commerce::class;

  protected function getExpectedConfig(): array {
    return [
      'php' => [
        'image' => 'tugboatqa/php:7.3-apache',
        'default' => TRUE,
        'depends' => 'mysql',
        'commands' => [
          'build' => [
            'composer self-update',
            'docker-php-ext-install opcache',
            'docker-php-ext-install bcmath',
            'a2enmod headers rewrite',
            'rm -rf "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'cd "${TUGBOAT_ROOT}" && composer create-project drupalcommerce/demo-project stm --stability dev --no-interaction',
            'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'php -d memory_limit=-1 stm/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
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
