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
  protected function getExpectedConfig(): array {
    return [
      'php' => [
        'image' => 'tugboatqa/php:8.2-apache',
        'default' => TRUE,
        'depends' => 'mysql',
        'commands' => [
          'build' => [
            'composer self-update',
            'docker-php-ext-install opcache',
            'a2enmod headers rewrite',
            'rm -rf "${DOCROOT}"',
            'echo "SIMPLYEST_STAGE_DOWNLOAD"',
            'git clone git@git.drupal.org:project/drupal_cms.git',
            "find \$TUGBOAT_ROOT/drupal_cms -type d -maxdepth 1 -name 'drupal_cms*' -exec composer config --global repositories.{} path {} ';'",
            'composer config --global repositories.template path $TUGBOAT_ROOT/drupal_cms/project_template',
            'composer create-project drupal/drupal-cms-project $TUGBOAT_ROOT/project --stability=dev',
            'ln -snf $TUGBOAT_ROOT/project/web $DOCROOT',
            'echo "SIMPLYEST_STAGE_PATCHING"',
            'cd stm && composer update --no-ansi',
            'echo "SIMPLYEST_STAGE_INSTALLING"',
            'cd "${DOCROOT}" && chmod -R 777 sites/default',
            'cd ${DOCROOT} && php -d memory_limit=-1 ../vendor/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Drupal CMS Demo"',
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
