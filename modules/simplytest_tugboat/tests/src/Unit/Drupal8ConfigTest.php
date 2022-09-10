<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Utility\Crypt;
use Drupal\simplytest_projects\ProjectTypes;

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
    yield '8.9.12 token' => [
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
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'cd "${DOCROOT}" && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'drush -r "${DOCROOT}" en token -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
    yield '8.9.12 token w/ patch' => [
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
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'cd "${DOCROOT}" && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'composer global require szeidler/composer-patches-cli:~1.0 --no-update',
              'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update',
              'composer global config --no-interaction allow-plugins.cweagans/composer-patches true',
              'cd "${DOCROOT}" && composer patch-enable --file="patches.json"',
              'cd "${DOCROOT}" && composer patch-add drupal/token "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'drush -r "${DOCROOT}" en token -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
    // @todo this asserts distros are broken as they are not downloaded but
    //   are the chosen install profile on `drush si`.
    yield 'panopoly distro' => [
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
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'cd "${DOCROOT}" && composer require drupal/panopoly:2.0-alpha15 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si panopoly --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
    yield 'perform_install false no drush si' => [
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
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'cd "${DOCROOT}" && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
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
              'echo "\$settings[\'hash_salt\'] = \'JzbemMqk0y1ALpbGBWhz8N_p9mr7wyYm_AQIpkxH1y-uSIGNTb5EnDwhJygBCyRKJhAOkQ1d7Q\';" >> ${DOCROOT}/sites/default/settings.php',
              'echo "\$settings[\'config_sync_directory\'] = \'sites/default/files/sync\';" >> ${DOCROOT}/sites/default/settings.php',
              'echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> ${DOCROOT}/sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
    // Test testing Drupal core with patches
    yield 'drupal core w/ patches' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        // NOTE: This is different on purpose, to verify it matches project_version.
        'drupal_core_version' => '8.9.0',
        'project_type' => ProjectTypes::CORE,
        'project_version' => '8.9.12',
        'project' => 'drupal',
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
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'composer global require szeidler/composer-patches-cli:~1.0 --no-update',
              'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update',
              'composer global config --no-interaction allow-plugins.cweagans/composer-patches true',
              'cd "${DOCROOT}" && composer patch-enable --file="patches.json"',
              'cd "${DOCROOT}" && composer patch-add drupal/core "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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

    yield '8.9.12 password_policy and password_policy_pwned' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '8.9.12',
        'project_type' => 'Module',
        'project_version' => '8.x-3.0-beta1',
        'project' => 'password_policy',
        'patches' => [],
        'additionals' => [
          [
            'version' => '8.x-1.0-beta2',
            'shortname' => 'password_policy_pwned',
            'patches' => [],
          ]
        ],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '8',
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
              'cd "${DOCROOT}" && git reset --hard 8.9.12',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'cd "${DOCROOT}" && composer require drupal/password_policy:3.0-beta1 --no-update',
              'cd "${DOCROOT}" && composer require drupal/password_policy_pwned:1.0-beta2 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'drush -r "${DOCROOT}" en password_policy -y',
              'drush -r "${DOCROOT}" en password_policy_pwned -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
    yield '8.9.x' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '8.9.x-dev',
        'project_type' => 'Drupal core',
        'project_version' => '8.9.x-dev',
        'project' => 'drupal',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '8',
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
              'cd "${DOCROOT}" && git reset --hard origin/8.9.x',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update',
              'cd "${DOCROOT}" && composer install --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'drush -r "${DOCROOT}" si standard --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
              'mkdir -p ${DOCROOT}/sites/default/files',
              'mkdir -p ${DOCROOT}/sites/default/files/private',
              'chown -R www-data:www-data ${DOCROOT}/sites/default',
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
