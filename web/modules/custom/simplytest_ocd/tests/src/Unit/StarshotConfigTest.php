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
            'php -m | grep -qi bcmath || docker-php-ext-install bcmath',
            'a2enmod headers rewrite',
            'command -v yq > /dev/null || (wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq)',
            'composer config --global policy.advisories.block false',
            'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini',
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
