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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'drush -r "${DOCROOT}" dl token-7.x-1.0 -y',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'drush -r "${DOCROOT}" en token -y',
              'cd "${DOCROOT}" && echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'drush -r "${DOCROOT}" dl token-7.x-1.0 -y',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}/sites/all/modules/token" && curl https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch | patch -p1',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'drush -r "${DOCROOT}" en token -y',
              'cd "${DOCROOT}" && echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si panopoly --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'drush -r "${DOCROOT}" dl token-7.x-1.0 -y',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && curl https://www.drupal.org/files/issues/2019-12-19/3077423-11.patch | patch -p1',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cp ${DOCROOT}/sites/default/default.settings.php ${DOCROOT}/sites/default/settings.php',
              'echo "\$databases[\'default\'][\'default\'] = [" >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'database\' => \'tugboat\'," >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'host\' => \'mysql\'," >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'username\' => \'tugboat\'," >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'password\' => \'tugboat\'," >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'port\' => 3306," >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'driver\' => \'mysql\'," >> ${DOCROOT}/sites/default/settings.php',
              'echo "     \'prefix\' => \'\'," >> ${DOCROOT}/sites/default/settings.php',
              'echo "];" >> ${DOCROOT}/sites/default/settings.php',
              'echo "\$drupal_hash_salt = \'JzbemMqk0y1ALpbGBWhz8N_p9mr7wyYm_AQIpkxH1y-uSIGNTb5EnDwhJygBCyRKJhAOkQ1d7Q\';" >> ${DOCROOT}/sites/default/settings.php',
              'echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> ${DOCROOT}/sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard 7.77',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && curl https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch | patch -p1',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
              'echo "SIMPLYEST_STAGE_FINALIZE"',
            ],
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ]
    ];
    yield '7.x-dev as main project' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '7.x-dev',
        'project_type' => 'Drupal core',
        'project_version' => '7.x-dev',
        'project' => 'drupal',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '7',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'cd "${DOCROOT}" && git config core.fileMode false',
              'cd "${DOCROOT}" && git fetch --all',
              'cd "${DOCROOT}" && git reset --hard origin/7.x',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
