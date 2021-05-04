<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Utility\Crypt;
use Drupal\simplytest_projects\ProjectTypes;

/**
 * Tests Drupal 7 preview config.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class Drupal7ConfigTest extends TugboatConfigTestBase {

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
        'drupal_core_version' => '7.77',
        'project_type' => 'Module',
        'project_version' => '7.x-1.0',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '7',
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
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'drush -r "${DOCROOT}" dl token-7.x-1.0 -y',
              'echo "SIMPLYEST_STAGE_PATCHING"',
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
        'drupal_core_version' => '7.77',
        'project_type' => 'Module',
        'project_version' => '7.x-1.0',
        'project' => 'token',
        'patches' => [
          'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch'
        ],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '7',
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
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'drush -r "${DOCROOT}" dl token-7.x-1.0 -y',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}/sites/all/modules/token" && curl https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch | patch -p1',
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
        'drupal_core_version' => '7.77',
        'project_type' => 'Distribution',
        'project_version' => '7.x-1.78',
        'project' => 'panopoly',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '7',
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
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'echo "SIMPLYEST_STAGE_PATCHING"',
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
        'drupal_core_version' => '7.77',
        'project_type' => 'Module',
        'project_version' => '7.x-1.0',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '7',
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
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'drush -r "${DOCROOT}" dl token-7.x-1.0 -y',
              'echo "SIMPLYEST_STAGE_PATCHING"',
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

    // Test Drupal core
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        // NOTE: This is different on purpose, to verify it matches project_version.
        'drupal_core_version' => '7.0',
        'project_type' => ProjectTypes::CORE,
        'project_version' => '7.77',
        'project' => 'drupal',
        'patches' => [
          'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch'
        ],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '7',
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
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && curl https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch | patch -p1',
              'cd "${DOCROOT}" && chmod -R 777 sites/default',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
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
  }


}
