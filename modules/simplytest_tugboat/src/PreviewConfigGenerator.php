<?php

namespace Drupal\simplytest_tugboat;

use Drupal\simplytest_ocd\OneClickDemoInterface;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;

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
    // @todo Why is 9 only on 7.3 and not 8? Also should it be 7.4?
    // @todo 7.2 is EOL; min should be 7.3.
    if ($parameters['major_version'] === '9') {
      $image_name = 'tugboatqa/php:7.3-apache';
    }
    else {
      $image_name = 'tugboatqa/php:7.2-apache';
    }

    // @todo we could have different Config classes, but this is an easy start.
    $build_commands = [
      ['composer self-update'],
      $this->getSetupCommands($parameters),
      $this->getDownloadCommands($parameters),
      ['echo "SIMPLYEST_STAGE_PATCHING"'],
      $this->getPatchingCommands($parameters),
      [
        // @todo this order should be flip flopped; it's this way for keeping
        //   the tests green during refactor.
        'cd "${DOCROOT}" && chmod -R 777 sites/default',
        'echo "SIMPLYEST_STAGE_INSTALLING"',
      ],
      $this->getInstallingCommands($parameters),
      ['echo "SIMPLYEST_STAGE_FINALIZE"'],
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
        'echo "SIMPLYEST_STAGE_INSTALLING"',
        'cd "${DOCROOT}" && chmod -R 777 sites/default',
      ],
      $one_click_demo->getInstallingCommands($parameters),
      [
        'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
        'echo "SIMPLYEST_STAGE_FINALIZE"'
      ],
    ];

    return [
      'services' => [
        'php' => [
          'image' => 'tugboatqa/php:7.3-apache',
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
    if ($parameters['major_version'] === '9') {
      $commands[] = 'rm -rf "${DOCROOT}"';
      // @todo drupal/recommended-project minimum stability is now stable
      //    add composer config minimum-stability dev and prefer-stable true
      $commands[] = sprintf('composer -n create-project drupal/recommended-project:%s stm --no-install', $parameters['drupal_core_version']);
      $commands[] = sprintf('cd stm && composer require --no-update drupal/core-recommended:%s', $parameters['drupal_core_version']);
      $commands[] = sprintf('cd stm && composer require --no-update drupal/core-composer-scaffold:%s', $parameters['drupal_core_version']);
      $commands[] = 'cd stm && composer require --dev --no-update drupal/dev-dependencies:dev-default';
      $commands[] = 'cd stm && composer require --no-update drush/drush:^10.0';
      $commands[] = 'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"';
    }
    else if ($parameters['major_version'] === '7' || $parameters['major_version'] === '8') {
      $commands[] = 'cd "${DOCROOT}" && git config core.fileMode false';
      $commands[] = 'cd "${DOCROOT}" && git fetch --all';
      if (substr($parameters['drupal_core_version'], -1) === 'x') {
        $commands[] = sprintf('cd "${DOCROOT}" && git reset --hard origin/%s', $parameters['drupal_core_version']);
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
    $is_distro = strtolower($parameters['project_type']) === 'distribution';
    if ($parameters['major_version'] === '9') {
      $commands[] = 'composer global require szeidler/composer-patches-cli:~1.0';
      $commands[] = 'cd stm && composer require cweagans/composer-patches:~1.0 --no-update';
      // @todo this should probably be removed, but it is kept for BC during the
      //   initial refactor (removing should fix distro instances)
      if ($is_distro) {
        return $commands;
      }
      $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-update', $parameters['project'], $parameters['project_version']);
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-update', $additional['shortname'], $additional['version']);
      }
      $commands[] = 'cd stm && composer update --no-ansi --no-dev';
    }
    else if ($parameters['major_version'] === '8') {
      $commands[] = 'composer global require szeidler/composer-patches-cli:~1.0';
      $commands[] = 'cd "${DOCROOT}" && composer require cweagans/composer-patches:~1.0 --no-update';
      $commands[] = 'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-update';
      $commands[] = 'cd "${DOCROOT}" && composer install --no-ansi --no-dev';
      // @todo this should probably be removed, but it is kept for BC during the
      //   initial refactor (removing should fix distro instances)
      if ($is_distro) {
        return $commands;
      }
      $commands[] = sprintf('cd "${DOCROOT}" && composer require drupal/%s:%s --no-update', $parameters['project'], $parameters['project_version']);
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-update', $additional['shortname'], $additional['version']);
      }
    }
    else if ($parameters['major_version'] === '7') {
      // @todo this should probably be removed, but it is kept for BC during the
      //   initial refactor (removing should fix distro instances)
      if ($is_distro) {
        return $commands;
      }
      $commands[] = sprintf('drush -r "${DOCROOT}" dl %s-%s -y', $parameters['project'], $parameters['project_version']);
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('drush -r "${DOCROOT}" dl %s-%s -y', $additional['shortname'], $additional['version']);
      }
    }
    return $commands;
  }

  private function getComposerPatchCommand($project_name, $patch) {
    return sprintf(
      'cd "${DOCROOT}" && composer patch-add drupal/%s "STM patch %s" "%s"',
      $project_name,
      basename($patch),
      $patch
    );
  }

  private function getLegacyPatchCommand($project_type, $project_name, $patch) {
    if ($project_type === 'drupal') {
      return sprintf('cd "${DOCROOT}" && curl %s | patch -p1', $patch);
    }
    return sprintf(
      'cd "${DOCROOT}/sites/all/%ss/%s" && curl %s | patch -p1',
      $project_type,
      $project_name,
      $patch
    );
  }

  private function getPatchingCommands(array $parameters) {
    $commands = [];

    if ($parameters['major_version'] === '8' || $parameters['major_version'] === '9') {
      $is_core = $parameters['project'] === 'drupal';
      // @todo previous version had cd DOCROOT vs cd stm, normalize.
      if (count($parameters['patches']) > 0) {
        $commands[] = 'cd "${DOCROOT}" && composer patch-enable --file="patches.json"';
      }
      foreach ($parameters['patches'] as $patch) {
        $commands[] = $this->getComposerPatchCommand($is_core ? 'core' : $parameters['project'], $patch);
      }
      foreach ($parameters['additionals'] as $additional) {
        foreach ($additional['patches'] as $additional_patch) {
          $commands[] = $this->getComposerPatchCommand($additional['shortname'], $additional_patch);
        }
      }
      $commands[] = 'cd "${DOCROOT}" && composer update --no-ansi --no-dev';
    }
    else if ($parameters['major_version'] === '7') {
      foreach ($parameters['patches'] as $patch) {
        $commands[] = $this->getLegacyPatchCommand(strtolower($parameters['project_type']), $parameters['project'], $patch);
      }
      foreach ($parameters['additionals'] as $additional) {
        foreach ($additional['patches'] as $additional_patch) {
          $commands[] = $this->getLegacyPatchCommand(strtolower($additional['type']), $additional['shortname'], $additional_patch);
        }
      }
    }
    return $commands;
  }

  private function getInstallingCommands(array $parameters) {
    $commands = [];
    if ($parameters['perform_install'] === FALSE) {
      return $commands;
    }

    $is_core = $parameters['project'] === 'drupal';
    $is_distro = strtolower($parameters['project_type']) === 'distribution';
    $install_profile = $parameters['install_profile'];
    if ($is_distro) {
      $install_profile = $parameters['project'];
    }

    if ($parameters['major_version'] === '9') {
      $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush si %s --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y', $install_profile);
      $commands[] = 'cd "${DOCROOT}" && chmod -R 777 sites/default/files';
      if (!$is_distro && !$is_core) {
        $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush en %s -y', $parameters['project']);
      }
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush %s -y', $additional['shortname']);
      }
    }
    else if ($parameters['major_version'] === '7' || $parameters['major_version'] === '8') {
      $commands[] = sprintf('drush -r "${DOCROOT}" si %s --account-name=admin --account-pass=admin -y', $install_profile);
      $commands[] = 'cd "${DOCROOT}" && chmod -R 777 sites/default/files';
      if (!$is_distro && !$is_core) {
        $commands[] = sprintf('drush -r "${DOCROOT}" en %s -y', $parameters['project']);
      }
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('drush -r "${DOCROOT}" en %s -y', $additional['shortname']);
      }
    }
    return $commands;
  }

}
