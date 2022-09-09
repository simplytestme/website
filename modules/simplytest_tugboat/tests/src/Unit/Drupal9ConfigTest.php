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
        'drupal_core_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
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
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'composer config --no-interaction allow-plugins.cweagans/composer-patches true',
              'cd stm && composer patch-enable --file="patches.json"',
              'cd stm && composer patch-add drupal/token "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
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
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/panopoly:2.0-alpha15 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si panopoly --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
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
    // When perform_install is false, `drush si` and `drush en` should not run.
    yield [
      [
        'perform_install' => FALSE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/token:1.9 --no-update',
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
    // Test specifying the install profile parameter.
    yield [
      [
        'perform_install' => TRUE,
        'install_profile' => 'demo_umami',
        'drupal_core_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
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
    // Test testing Drupal core with patches
    yield 'drupal core with patches and umami' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'demo_umami',
        // NOTE: This is different on purpose, to verify it matches project_version.
        'drupal_core_version' => '9.0.0',
        'project_type' => ProjectTypes::CORE,
        'project_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/core:9.3.2 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'composer global require szeidler/composer-patches-cli:~1.0',
              'cd stm && composer require cweagans/composer-patches:~1.0 --no-update',
              'composer config --no-interaction allow-plugins.cweagans/composer-patches true',
              'cd stm && composer patch-enable --file="patches.json"',
              'cd stm && composer patch-add drupal/core "STM patch 3185080-3.patch" "https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
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
    yield 'password_policy and password_policy_pwned' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.2',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/password_policy:3.0-beta1 --no-update',
              'cd stm && composer require drupal/password_policy_pwned:1.0-beta2 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en password_policy -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en password_policy_pwned -y',
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
    yield 'less than 9.1.6 no phpspec/prophecy-phpunit' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.1.5',
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
          'image' => 'tugboatqa/php:7.4-apache',
          'default' => true,
          'depends' => 'mysql',
          'commands' => [
            'build' => [
              'docker-php-ext-install opcache',
              'a2enmod headers rewrite',
              'composer self-update',
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.5 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.1.5 drupal/core-dev:9.1.5',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
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
    yield '9.3.x' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.x-dev',
        'project_type' => 'Drupal core',
        'project_version' => '9.3.x-dev',
        'project' => 'drupal',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
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
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.x-dev stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.x-dev drupal/core-dev:9.3.x-dev',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/core:9.3.x-dev --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
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
    yield 'core version is respected 9.1.9' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.1.9',
        'project_type' => 'Drupal core',
        'project_version' => '9.1.9',
        'project' => 'drupal',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
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
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.1.9 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.1.9 drupal/core-dev:9.1.9',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/core:9.1.9 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
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
    yield 'theme:enable is used for themes' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.2',
        'project_type' => 'Theme',
        'project_version' => '8.x-3.24',
        'project' => 'bootstrap',
        'patches' => [],
        'additionals' => [],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
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
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.2 drupal/core-dev:9.3.2',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/bootstrap:3.24 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush theme:enable bootstrap -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme default bootstrap -y',
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
    yield '9.3.x with additionals' => [
      [
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '9.3.x-dev',
        'project_type' => 'Drupal core',
        'project_version' => '9.3.x-dev',
        'project' => 'drupal',
        'patches' => [],
        'additionals' => [
          [
            'version' => '8.x-1.9',
            'shortname' => 'token',
            'patches' => [],
            'project_type' => 'Module',
          ],
          [
            'version' => '8.x-2.4',
            'shortname' => 'uswds',
            'patches' => [],
            'project_type' => 'Theme',
          ],
        ],
        'instance_id' => $instance_id,
        'hash' => $hash,
        'major_version' => '9',
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
              'rm -rf "${DOCROOT}"',
              'composer -n create-project drupal/recommended-project:9.3.x-dev stm --no-install',
              'cd stm && composer config minimum-stability dev',
              'cd stm && composer config prefer-stable true',
              'cd stm && composer require --dev --no-update drupal/core:9.3.x-dev drupal/core-dev:9.3.x-dev',
              'cd stm && composer require --dev --no-update phpspec/prophecy-phpunit:^2',
              'cd stm && composer require --no-update drush/drush',
              'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
              'echo "SIMPLYEST_STAGE_DOWNLOAD"',
              'cd stm && composer require drupal/core:9.3.x-dev --no-update',
              'cd stm && composer require drupal/token:1.9 --no-update',
              'cd stm && composer require drupal/uswds:2.4 --no-update',
              'echo "SIMPLYEST_STAGE_PATCHING"',
              'cd "${DOCROOT}" && composer update --no-ansi',
              'echo "SIMPLYEST_STAGE_INSTALLING"',
              'cd "${DOCROOT}" && ../vendor/bin/drush si standard --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush theme:enable uswds -y',
              'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme default uswds -y',
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
