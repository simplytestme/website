<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\Plugin\OneClickDemo\Commerce;

/**
 *
 */
final class CommerceConfigTest extends OneClickDemoConfigTestBase {

  protected static string $pluginId = 'oneclickdemo_commerce';
  protected static string $pluginClass = Commerce::class;

  /**
   *
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
            'docker-php-ext-install bcmath',
            'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini',
            'a2enmod headers rewrite',
            'rm -rf "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'cd "${TUGBOAT_ROOT}" && composer create-project centarro/commerce-kickstart-project stm --no-install --stability dev --no-interaction',
            'cd "${TUGBOAT_ROOT}/stm" && composer config bin-dir --unset',
            'cd "${TUGBOAT_ROOT}/stm" && composer install',
            'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> ${DOCROOT}/sites/default/settings.php',
            'cd "${DOCROOT}" && ../vendor/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
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
