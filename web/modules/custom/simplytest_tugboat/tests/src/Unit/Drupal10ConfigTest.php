<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Utility\Crypt;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal 9 preview config.
 */
#[Group('simplytest')]
#[Group('simplytest_tugboat')]
final class Drupal10ConfigTest extends TugboatConfigTestBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function configData(): \Generator {
    $instance_id = Crypt::randomBytesBase64();
    $hash = Crypt::randomBytesBase64();
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '10.0.0-alpha3',
        'project_type' => 'Module',
        'project_version' => '8.x-1.x-dev',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => 10,
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:8.2-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'php -m | grep -qi bcmath || docker-php-ext-install bcmath',
              'a2enmod headers rewrite',
              'command -v yq > /dev/null || (wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq)',
              'composer config --global policy.advisories.block false',
              '[ "$(cd "${TUGBOAT_ROOT}/stm" 2>/dev/null && composer show drupal/core --format=json 2>/dev/null | jq -r \'.versions[0]\')" = "10.0.0-alpha3" ] && echo "Reusing Drupal 10.0.0-alpha3 from the base preview" || (rm -rf "${DOCROOT}" "${TUGBOAT_ROOT}/stm" && cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/recommended-project:10.0.0-alpha3 stm --no-install && cd "${TUGBOAT_ROOT}/stm" && composer config minimum-stability dev && cd "${TUGBOAT_ROOT}/stm" && composer config prefer-stable true && cd "${TUGBOAT_ROOT}/stm" && composer require --no-install drush/drush && ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}")',
              'cd "${TUGBOAT_ROOT}/stm" && composer require --dev --no-install drupal/core:10.0.0-alpha3',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/token:1.x-dev --no-install',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
              'chown -R www-data:www-data ${DOCROOT}/modules',
              'echo "max_allowed_packet=33554432" >> /etc/my.cnf',
              'echo "SIMPLYEST_STAGE_FINALIZE"',
            ],
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ]
    ];
  }

}
