<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Utility\Crypt;

/**
 * Tests Drupal 9 preview config.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class Drupal9ConfigTest extends TugboatConfigTestBase {

  /**
   * {@inheritdoc}
   */
  public function configData(): \Generator {
    $instance_id = Crypt::randomBytesBase64();
    $hash = Crypt::randomBytesBase64();
    yield [
      [
        'perform_install' => TRUE,
        'drupal_core_version' => '9.1.2',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.3-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --no-update drupal/core-recommended:9.1.2',
              'cd stm && composer require --no-update drupal/core-composer-scaffold:9.1.2',
              'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default',
              'cd stm && composer require --dev --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:8.x-1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && chmod -R 777 sites/default/files',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'echo "SIMPLYEST_STAGE_FINALIZE"',
            ],
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ]
    ];
    yield [
      [
        'perform_install' => TRUE,
        'drupal_core_version' => '9.1.2',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [
          'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch'
        ],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.3-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --no-update drupal/core-recommended:9.1.2',
              'cd stm && composer require --no-update drupal/core-composer-scaffold:9.1.2',
              'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default',
              'cd stm && composer require --dev --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:8.x-1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer patch-enable --file="patches.json"',
              'cd "${DOCROOT}" && composer patch-add drupal/token "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd stm && composer update --no-ansi',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && chmod -R 777 sites/default/files',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'echo "SIMPLYEST_STAGE_FINALIZE"',
            ],
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ]
    ];
    // @todo this asserts distros are broken as they are not downloaded but
    //   are the chosen install profile on `drush si`.
    yield [
      [
        'perform_install' => TRUE,
        'drupal_core_version' => '9.1.2',
        'project_type' => 'Distribution',
        'project_version' => '8.x-2.0-alpha15',
        'project' => 'panopoly',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.3-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --no-update drupal/core-recommended:9.1.2',
              'cd stm && composer require --no-update drupal/core-composer-scaffold:9.1.2',
              'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default',
              'cd stm && composer require --dev --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si panopoly --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && chmod -R 777 sites/default/files',
              'echo "SIMPLYEST_STAGE_FINALIZE"',
            ],
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ]
    ];
    // When perform_install is false, `drush si` and `drush en` should not run.
    yield [
      [
        'perform_install' => FALSE,
        'drupal_core_version' => '9.1.2',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.3-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --no-update drupal/core-recommended:9.1.2',
              'cd stm && composer require --no-update drupal/core-composer-scaffold:9.1.2',
              'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default',
              'cd stm && composer require --dev --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:8.x-1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
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
