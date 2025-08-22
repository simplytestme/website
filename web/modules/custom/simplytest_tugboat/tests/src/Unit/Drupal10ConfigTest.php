<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Utility\Crypt;
use Drupal\simplytest_projects\ProjectTypes;

/**
 * Tests Drupal 9 preview config.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class Drupal10ConfigTest extends TugboatConfigTestBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function configData(): \Generator {
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
              'docker-php-ext-install bcmath',
              'a2enmod headers rewrite',
              'wget https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:10.0.0-alpha3 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-install drupal/core:10.0.0-alpha3 drupal/core-dev:10.0.0-alpha3',
              'cd stm && composer require --dev --no-install phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-install drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
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
