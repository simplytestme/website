<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\Starshot;

/**
 *
 */
final class StarshotConfigTest extends OneClickDemoConfigTestBase {

  protected static string $pluginId = 'starshot';
  protected static string $pluginClass = Starshot::class;

  /**
   * @return array<string, mixed>
   */
  #[\Override]
  protected function getExpectedConfig(): array {
    return [
      'php' => [
        'image' => 'tugboatqa/php:8.3-apache',
        'default' => TRUE,
        'depends' => 'mysql',
        'commands' => [
          'build' => [
            'composer self-update',
            'docker-php-ext-install opcache',
            'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini',
            'a2enmod headers rewrite',
            'rm -rf "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'composer create-project drupal/cms $TUGBOAT_ROOT/stm',
            'ln -snf $TUGBOAT_ROOT/stm/web $DOCROOT',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'cd ${DOCROOT} && ../vendor/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Drupal CMS Demo"',
            'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
            'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
            'echo "SIMPLYEST_STAGE_FINALIZE"',
          ],
        ],
      ],
      'mysql' => [
        'image' => 'tugboatqa/mysql:8',
      ],
    ];
  }

}
