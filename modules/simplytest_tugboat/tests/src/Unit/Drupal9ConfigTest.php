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
        'install_profile' => 'standard',
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
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'mkdir -p ${DOCROOT}/sites/default/files',
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
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer patch-enable --file="patches.json"',
              'cd stm && composer patch-add drupal/token "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'mkdir -p ${DOCROOT}/sites/default/files',
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
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/panopoly:2.0-alpha15 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si panopoly --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'mkdir -p ${DOCROOT}/sites/default/files',
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
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
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
              'mkdir -p ${DOCROOT}/sites/default/files',
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
    // Test specifying the install profile parameter.
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'demo_umami',
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
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'mkdir -p ${DOCROOT}/sites/default/files',
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
    // Test testing Drupal core with patches
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'demo_umami',
        // NOTE: This is different on purpose, to verify it matches project_version.
        'drupal_core_version' => '9.0.0',
        'project_type' => ProjectTypes::CORE,
        'project_version' => '9.1.2',
        'project' => 'drupal',
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
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/core:9.1.2 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer patch-enable --file="patches.json"',
              'cd stm && composer patch-add drupal/core "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'mkdir -p ${DOCROOT}/sites/default/files',
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
    yield 'password_policy and password_policy_pwned' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.1.2',
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
        'major_version' => '9',
      ],
      [
        'php' => [
          'image' => 'tugboatqa/php:7.3-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.2 stm --no-install',
              'cd stm && composer require --dev --no-update drupal/core-dev:9.1.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush:^10.0',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'cd stm && composer require drupal/password_policy:3.0-beta1 --no-update',
              'cd stm && composer require drupal/password_policy_pwned:1.0-beta2 --no-update',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd stm && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en password_policy -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en password_policy_pwned -y',
              'mkdir -p ${DOCROOT}/sites/default/files',
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
