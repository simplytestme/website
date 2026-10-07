<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\Dashi;

final class DashiConfigTest extends OneClickDemoConfigTestBase {

  protected static string $pluginId = 'oneclickdemo_dashi';
  protected static string $pluginClass = Dashi::class;

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
            'php -m | grep -qi mysqli || docker-php-ext-install mysqli',
            'a2enmod headers rewrite',
            'command -v yq > /dev/null || (wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq)',
            'composer config --global policy.advisories.block false',
            'echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/my-php.ini',
            'rm -rf "${DOCROOT}"',
            'cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/cms:^2 stm --no-install',
            'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'cd "${TUGBOAT_ROOT}/stm" && composer require --no-update drush/drush drupal/dashi:^1',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/dashi --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name=Dashi',
            'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
            'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
            'echo "SIMPLYEST_STAGE_FINALIZE"',
            'cd "${DOCROOT}" && echo "SIMPLYTEST_LOGIN_URL $(../vendor/bin/drush uli --uri="${TUGBOAT_DEFAULT_SERVICE_URL}" --no-browser /)"',
          ],
        ],
      ],
      'mysql' => [
        'image' => 'tugboatqa/mysql:8',
      ],
    ];
  }

}
