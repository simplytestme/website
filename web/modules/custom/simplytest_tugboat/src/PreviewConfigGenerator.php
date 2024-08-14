<?php

namespace Drupal\simplytest_tugboat;

use Composer\Semver\Semver;
use Drupal\simplytest_ocd\OneClickDemoInterface;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_projects\ProjectTypes;

/**
 * Generates preview configurations for Tugboat.
 */
final class PreviewConfigGenerator {

  /**
   * The one click demo manager.
   *
   * @var \Drupal\simplytest_ocd\OneClickDemoPluginManager
   */
  private $oneClickDemoManager;

  /**
   * Constructs a new PreviewConfigGenerator object.
   *
   * @param \Drupal\simplytest_ocd\OneClickDemoPluginManager $one_click_demo_manager
   *   The one click demo manager.
   */
  public function __construct(OneClickDemoPluginManager $one_click_demo_manager) {
    // @todo what if all builds were a plugin – so D7, D8, D9, Umami, Commerce?
    $this->oneClickDemoManager = $one_click_demo_manager;
  }

  /**
   * Generates a preview configuration based on the provided parameters.
   *
   * @param array $parameters
   *   The preview config parameters.
   *
   * @return array
   *   The preview config.
   */
  public function generate(array $parameters): array {
    // @todo make these configurable in #3236528
    // @see https://www.drupal.org/project/simplytest/issues/
    if ($parameters['major_version'] === '10') {
      $image_name = 'tugboatqa/php:8.1-apache';
    }
    elseif ($parameters['major_version'] === '9') {
      $image_name = 'tugboatqa/php:7.4-apache';
    }
    else {
      $image_name = 'tugboatqa/php:7.4-apache';
    }

    // Rename drupal to core so that it becomes drupal/core as a package name.
    // Have core version match the selected project version, as they user may
    // not have opened advanced options.
    if ($parameters['project_type'] === ProjectTypes::CORE) {
      $parameters['project'] = 'core';
      $parameters['drupal_core_version'] = $parameters['project_version'];
    }

    // @todo we could have different Config classes, but this is an easy start.
    $build_commands = [
      // @todo these belong in a base preview.
      [
        'docker-php-ext-install opcache',
        'a2enmod headers rewrite'
      ],
      ['composer self-update'],
      $this->getSetupCommands($parameters),
      $this->getDownloadCommands($parameters),
      ['echo "SIMPLYEST_STAGE_PATCHING"'],
      $this->getPatchingCommands($parameters),
    ];

    if(in_array($parameters['major_version'], ['10', '9'])) {
      $build_commands[] = ['cd stm && composer update --no-ansi'];
    } else if ($parameters['major_version'] == '8') {
      $build_commands[] = ['cd "${DOCROOT}" && composer update --no-ansi'];
    }

    $build_commands[] = ['echo "SIMPLYEST_STAGE_INSTALLING"'];
    $build_commands[] = $this->getInstallingCommands($parameters);
    $build_commands[] = [
      'mkdir -p ${DOCROOT}/sites/default/files',
      'mkdir -p ${DOCROOT}/sites/default/files/private',
      'chown -R www-data:www-data ${DOCROOT}/sites/default',
      'chown -R www-data:www-data ${DOCROOT}/modules',
      'echo "max_allowed_packet=33554432" >> /etc/my.cnf',
      'echo "SIMPLYEST_STAGE_FINALIZE"'
    ];

    return [
      'services' => [
        'php' => [
          'image' => $image_name,
          'default' => TRUE,
          'depends' => 'mysql',
          'commands' => [
            'build' => array_merge(...$build_commands),
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ],
    ];
  }

  /**
   * Generate a preview config for a One Click Demo.
   *
   * @param string $demo_id
   *   The demo name.
   * @param array $parameters
   *   The preview config parameters.
   *
   * @return array
   *   The preview config.
   */
  public function oneClickDemo(string $demo_id, array $parameters): array {
    $one_click_demo = $this->oneClickDemoManager->createInstance($demo_id);
    assert($one_click_demo instanceof OneClickDemoInterface);

    // @todo all things should be build plugins, normalize with ::generate.
    $build_commands = [
      ['composer self-update'],
      $one_click_demo->getSetupCommands($parameters),
      ['echo "SIMPLYEST_STAGE_DOWNLOAD"'],
      $one_click_demo->getDownloadCommands($parameters),
      ['echo "SIMPLYEST_STAGE_PATCHING"'],
      $one_click_demo->getPatchingCommands($parameters),
      [
        'cd stm && composer update --no-ansi',
        'echo "SIMPLYEST_STAGE_INSTALLING"',
        'cd "${DOCROOT}" && chmod -R 777 sites/default',
      ],
      $one_click_demo->getInstallingCommands($parameters),
      [
        'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
        'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
        'echo "SIMPLYEST_STAGE_FINALIZE"',
      ],
    ];

    return [
      'services' => [
        'php' => [
          'image' => 'tugboatqa/php:8.1-apache',
          'default' => TRUE,
          'depends' => 'mysql',
          'commands' => [
            'build' => array_merge(...$build_commands),
          ],
        ],
        'mysql' => [
          'image' => 'tugboatqa/mysql:5',
        ],
      ],
    ];
  }

  private function getSetupCommands(array $parameters) {
    $commands = [];
    if ($parameters['major_version'] === '10' || $parameters['major_version'] === '9') {
      $commands[] = 'rm -rf "${DOCROOT}"';
      $commands[] = sprintf('composer -n create-project drupal/recommended-project:%s stm --no-install', $parameters['drupal_core_version']);
      $commands[] = 'cd stm && composer config minimum-stability dev';
      $commands[] = 'cd stm && composer config prefer-stable true';
      // We need to require drupal/core and drupal/core-dev at the requested
      // Drupal core version, otherwise `composer update` could bump the
      // versions to the latest available one.
      $commands[] = sprintf('cd stm && composer require --dev --no-install drupal/core:%1$s drupal/core-dev:%1$s', $parameters['drupal_core_version']);
      // The phpspec/prophecy-phpunit check was added in 9.1.6
      // @see https://www.drupal.org/i/3182653
      // @see https://git.drupalcode.org/project/drupal/-/commit/94d0c1f
      if (Semver::satisfies($parameters['drupal_core_version'], '>=9.1.6')) {
        $commands[] = 'cd stm && composer require --dev --no-install phpspec/prophecy-phpunit:^2';
      }
      $commands[] = 'cd stm && composer require --no-install drush/drush';
      $commands[] = 'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"';
    }
    else if ($parameters['major_version'] === '7' || $parameters['major_version'] === '8') {
      $commands[] = 'cd "${DOCROOT}" && git config core.fileMode false';
      $commands[] = 'cd "${DOCROOT}" && git fetch --all';

      if (substr($parameters['drupal_core_version'], -4) === '-dev') {
        $commands[] = sprintf('cd "${DOCROOT}" && git reset --hard origin/' . substr($parameters['drupal_core_version'], 0, -4));
      }
      else {
        $commands[] = sprintf('cd "${DOCROOT}" && git reset --hard %s', $parameters['drupal_core_version']);
      }
    }

    return $commands;
  }

  private function getDownloadCommands(array $parameters) {
    $commands = [
      'echo "SIMPLYEST_STAGE_DOWNLOAD"',
    ];
    $is_core = $parameters['project_type'] === ProjectTypes::CORE;
    $is_distro = $parameters['project_type'] === ProjectTypes::DISTRO;

    if ($parameters['major_version'] === '10' || $parameters['major_version'] === '9') {
      $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-install', $parameters['project'], $this->getComposerCompatibleVersionString($parameters['project_version']));
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-install', $additional['shortname'], $this->getComposerCompatibleVersionString($additional['version']));
      }
    }
    else if ($parameters['major_version'] === '8') {
      $commands[] = 'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-install';
      $commands[] = 'cd "${DOCROOT}" && composer install --no-ansi';
      if (!$is_core) {
        $commands[] = sprintf('cd "${DOCROOT}" && composer require drupal/%s:%s --no-install', $parameters['project'], $this->getComposerCompatibleVersionString($parameters['project_version']));
      }
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd "${DOCROOT}" && composer require drupal/%s:%s --no-install', $additional['shortname'], $this->getComposerCompatibleVersionString($additional['version']));
      }
    }
    else if ($parameters['major_version'] === '7') {
      // @todo this should probably be removed, but it is kept for BC during the
      //   initial refactor (removing should fix distro instances)
      // @note Drupal 7 + distro might be too hard.
      if ($is_distro || $is_core) {
        return $commands;
      }
      $commands[] = sprintf('drush -r "${DOCROOT}" dl %s-%s -y', $parameters['project'], $parameters['project_version']);
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('drush -r "${DOCROOT}" dl %s-%s -y', $additional['shortname'], $additional['version']);
      }
    }
    return $commands;
  }

  private function getComposerPatchCommand(string $project_name, string $patch, string $dir = 'stm') {
    return sprintf(
      'cd %s && composer patch-add drupal/%s "STM patch %s" "%s" --no-update',
      $dir,
      $project_name,
      basename($patch),
      $patch
    );
  }

  private function getLegacyPatchCommand($project_type, $project_name, $patch) {
    if ($project_type === ProjectTypes::CORE) {
      return sprintf('cd "${DOCROOT}" && curl %s | patch -p1', $patch);
    }

    if ($project_type === ProjectTypes::DISTRO) {
      return sprintf('cd "${DOCROOT}/profiles/%s" && curl %s | patch -p1',
        $project_name,
        $patch,
      );
    }
    $directory = '';
    if ($project_type === ProjectTypes::MODULE) {
      $directory = 'modules';
    }
    elseif ($project_type === ProjectTypes::THEME) {
      $directory = 'themes';
    }
    else {
      // @todo exception or log?
      return 'echo "Could not determine how to patch"';
    }

    return sprintf(
      'cd "${DOCROOT}/sites/all/%s/%s" && curl %s | patch -p1',
      $directory,
      $project_name,
      $patch
    );
  }

  private function getPatchingCommands(array $parameters) {
    $commands = [];
    // check if we need patching.
    $empty = TRUE;
    if (!empty($parameters['patches'])) {
      $empty = FALSE;
    } else {
      foreach ($parameters['additionals'] as $additional) {
        if (!empty($additional['patches'])) {
          $empty = FALSE;
        }
      }
    }
    // bail if empty.
    if ($empty) {
      return [];
    }
    // perform patching conditionally for major drupal version.
    switch ($parameters['major_version']) {
      case '7':
        // Patch Drupal 7 to automatically redirect to the installer.
        if ($parameters['perform_install'] === FALSE) {
          $commands[] = $this->getLegacyPatchCommand(ProjectTypes::CORE, '', 'https://www.drupal.org/files/issues/2019-12-19/3077423-11.patch');
        }
        foreach ($parameters['patches'] as $patch) {
          $commands[] = $this->getLegacyPatchCommand($parameters['project_type'], $parameters['project'], $patch);
        }
        foreach ($parameters['additionals'] as $additional) {
          foreach ($additional['patches'] as $additional_patch) {
            $commands[] = $this->getLegacyPatchCommand($additional['type'], $additional['shortname'], $additional_patch);
          }
        }
        break;
      default:
        $composerWorkingDir = $parameters['major_version'] !== '8' ? 'stm' : '"${DOCROOT}"';
        $commands[] = 'composer global config --no-interaction allow-plugins.szeidler/composer-patches-cli true';
        $commands[] = 'composer global config --no-interaction allow-plugins.cweagans/composer-patches true';
        $commands[] = 'composer global require szeidler/composer-patches-cli:~1.0';
        $commands[] = 'cd ' . $composerWorkingDir .  ' && composer patch-enable --file="patches.json"';
        foreach ($parameters['patches'] as $patch) {
          $commands[] = $this->getComposerPatchCommand($parameters['project'], $patch, $composerWorkingDir);
        }
        foreach ($parameters['additionals'] as $additional) {
          foreach ($additional['patches'] as $additional_patch) {
            $commands[] = $this->getComposerPatchCommand($additional['shortname'], $additional_patch, $composerWorkingDir);
          }
        }
        break;
    }

    return $commands;
  }

  private function getInstallingCommands(array $parameters): array {
    $commands = [];
    if ($parameters['perform_install'] === FALSE) {
      $commands[] = 'cp ${DOCROOT}/sites/default/default.settings.php ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "\$databases[\'default\'][\'default\'] = [" >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'database\' => \'tugboat\'," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'host\' => \'mysql\'," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'username\' => \'tugboat\'," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'password\' => \'tugboat\'," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'port\' => 3306," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'driver\' => \'mysql\'," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "     \'prefix\' => \'\'," >> ${DOCROOT}/sites/default/settings.php';
      $commands[] = 'echo "];" >> ${DOCROOT}/sites/default/settings.php';

      // Provide a hash salt so that installation begins automatically.
      // @see install_begin_request
      if ($parameters['major_version'] === '10' || $parameters['major_version'] === '9' || $parameters['major_version'] === '8') {
        $commands[] = 'echo "\$settings[\'hash_salt\'] = \'JzbemMqk0y1ALpbGBWhz8N_p9mr7wyYm_AQIpkxH1y-uSIGNTb5EnDwhJygBCyRKJhAOkQ1d7Q\';" >> ${DOCROOT}/sites/default/settings.php';
        $commands[] = 'echo "\$settings[\'config_sync_directory\'] = \'sites/default/files/sync\';" >> ${DOCROOT}/sites/default/settings.php';
        $commands[] = 'echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> ${DOCROOT}/sites/default/settings.php';
      }
      else {
        $commands[] = 'echo "\$drupal_hash_salt = \'JzbemMqk0y1ALpbGBWhz8N_p9mr7wyYm_AQIpkxH1y-uSIGNTb5EnDwhJygBCyRKJhAOkQ1d7Q\';" >> ${DOCROOT}/sites/default/settings.php';
        $commands[] = 'echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> ${DOCROOT}/sites/default/settings.php';
      }
      return $commands;
    }

    $is_core = $parameters['project_type'] === ProjectTypes::CORE;
    $is_distro = $parameters['project_type'] === ProjectTypes::DISTRO;
    $install_profile = $parameters['install_profile'];
    if ($is_distro) {
      $install_profile = $parameters['project'];
    }

    if ($parameters['major_version'] === '10' || $parameters['major_version'] === '9') {
      $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush si %s --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y', $install_profile);
      // Enable verbose error reporting.
      $commands[] = 'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y';
      if (!$is_distro && !$is_core) {
        $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush %s %s -y', $parameters['project_type'] === ProjectTypes::THEME ? 'theme:enable' : 'en', $parameters['project']);
      }
      // If this is a theme, and it is not the Gin admin theme, set it as the
      // default theme.
      if ($parameters['project_type'] === ProjectTypes::THEME) {
        $commands[] = sprintf(
          'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme %s %s -y',
          $parameters['project'] === 'gin' ? 'admin' : 'default',
          $parameters['project']
        );
      }
      foreach ($parameters['additionals'] as $additional) {
        $additional_product_type = $additional['project_type'] ?? ProjectTypes::MODULE;
        $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush %s %s -y', $additional_product_type === ProjectTypes::THEME ? 'theme:enable' : 'en', $additional['shortname']);
        if ($additional_product_type === ProjectTypes::THEME) {
          $commands[] = sprintf(
            'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme %s %s -y',
            $additional['shortname'] === 'gin' ? 'admin' : 'default',
            $additional['shortname']
          );
        }
      }
    }
    else if ($parameters['major_version'] === '7' || $parameters['major_version'] === '8') {
      $commands[] = sprintf('drush -r "${DOCROOT}" si %s --account-name=admin --account-pass=admin -y', $install_profile);
      if ($parameters['major_version'] === '8') {
        $commands[] = 'drush -r "${DOCROOT}" config-set system.logging error_level verbose -y';
      }
      if (!$is_distro && !$is_core) {
        $commands[] = sprintf('drush -r "${DOCROOT}" en %s -y', $parameters['project']);
      }
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('drush -r "${DOCROOT}" en %s -y', $additional['shortname']);
      }
    }

    if ($parameters['major_version'] === '7') {
      $commands[] = 'cd "${DOCROOT}" && echo \'$conf["file_private_path"] = "sites/default/files/private";\'  >> sites/default/settings.php';
    }
    else {
      $commands[] = 'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php';
    }
    return $commands;
  }

  /**
   * Converts Drupal version strings to semver compatible ones for Composer
   *
   * Legacy: 8.x-1.3 becomes 1.3, same was 8.x-1.x-dev becomes 1.x-dev
   *
   * @param string $version
   *   The version.
   *
   * @return string
   *   The compatible version.
   */
  private function getComposerCompatibleVersionString(string $version): string {
    // Check if the version is a contrib extension using the legacy core prefix
    // versioning.
    $legacy_matches = [];
    $result = preg_match('/^[7|8].x-(.*)$/', $version, $legacy_matches);
    if ($result === 1 && count($legacy_matches) === 2) {
      return $legacy_matches[1];
    }
    return $version;
  }

}
