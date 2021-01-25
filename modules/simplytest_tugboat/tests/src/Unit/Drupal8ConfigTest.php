<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Utility\Crypt;

/**
 * Tests Drupal 8 preview config.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class Drupal8ConfigTest extends TugboatConfigTestBase {

  /**
   * {@inheritdoc}
   */
  public function configData(): \Generator {
    $instance_id = Crypt::randomBytesBase64();
    $hash = Crypt::randomBytesBase64();
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '8.9.12',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '8',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.2-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi --no-dev',
              'cd "${DOCROOT}" && composer require drupal/token:8.x-1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi --no-dev',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && chmod -R 777 sites/default/files',
              'drush -r "${DOCROOT}" en token -y',
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
        'install_profile' => 'standard',
        'drupal_core_version' => '8.9.12',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [
          'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch'
        ],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '8',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.2-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi --no-dev',
              'cd "${DOCROOT}" && composer require drupal/token:8.x-1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer patch-enable --file="patches.json"',
              'cd "${DOCROOT}" && composer patch-add drupal/token "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd "${DOCROOT}" && composer update --no-ansi --no-dev',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && chmod -R 777 sites/default/files',
              'drush -r "${DOCROOT}" en token -y',
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
        'install_profile' => 'standard',
        'drupal_core_version' => '8.9.12',
        'project_type' => 'Distribution',
        'project_version' => '8.x-2.0-alpha15',
        'project' => 'panopoly',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '8',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.2-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi --no-dev',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi --no-dev',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si panopoly --account-name=admin --account-pass=admin -y',
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
        'install_profile' => 'standard',
        'drupal_core_version' => '8.9.12',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '8',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.2-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi --no-dev',
              'cd "${DOCROOT}" && composer require drupal/token:8.x-1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi --no-dev',
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
