<?php

namespace Drupal\simplytest_tugboat;

use Composer\Semver\Semver;
use Drupal\simplytest_ocd\OneClickDemoInterface;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_projects\ProjectTypes;

/**
 * Generates preview configurations for Tugboat.
 */
final readonly class PreviewConfigGenerator {

  /**
   * The composer-patches requirement added to generated builds.
   *
   * Pinned on purpose. This used to arrive as a transitive dependency of
   * szeidler/composer-patches-cli, which widened to `^1.7 || ^2.0` and silently
   * moved every sandbox from 1.x to 2.x, changing how patches are applied.
   *
   * @see https://www.drupal.org/project/simplytest/issues/3588836
   */
  private const string COMPOSER_PATCHES = 'cweagans/composer-patches:^2.0';

  /**
   * Patcher configuration that falls back to GNU patch.
   *
   * composer-patches 2.x only ships git-based patchers, and `git apply` has no
   * fuzz: a patch whose context drifted by a line is rejected outright. GNU
   * patch still applies those, which is what sandboxes did before 2.x. It runs
   * only after the git patchers have refused the patch.
   *
   * The arguments are templates. composer-patches fills the three `%s` with the
   * patch depth, the package directory, and the downloaded patch file.
   *
   * @see \cweagans\Composer\Patcher\FreeformPatcher
   */
  /**
   * Stops Composer refusing releases that have security advisories.
   *
   * Composer 2.10 blocks any package version with a known advisory, and the
   * Tugboat images ship it. Installing an old core release is the point of a
   * sandbox, so the block is turned off globally, where the base preview and
   * the sandbox built on it both see it.
   */
  private const string ALLOW_ADVISORIES = 'composer config --global policy.advisories.block false';

  /**
   * What a sandbox needs from its environment, done in the base preview.
   *
   * Each step is skipped when the base already did it, which is the normal
   * case. When no base is available the sandbox builds from the bare image,
   * and these make that build succeed rather than fail somewhere later.
   * Compiling bcmath alone is a fifth of a sandbox build.
   */
  private const array ENVIRONMENT = [
    'php -m | grep -qi bcmath || docker-php-ext-install bcmath',
    'a2enmod headers rewrite',
    'command -v yq > /dev/null || (wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq)',
    self::ALLOW_ADVISORIES,
  ];

  private const array FREEFORM_PATCHER = [
    'executable' => 'patch',
    'dry_run_args' => '-p%s -d %s --dry-run --no-backup-if-mismatch -i %s',
    'args' => '-p%s -d %s --no-backup-if-mismatch -i %s',
  ];

  /**
   * The Docker images every one click demo builds on.
   *
   * Shared between the demo config and its base preview, since a sandbox only
   * inherits a base preview when both use the same images.
   */
  private const array ONE_CLICK_DEMO_IMAGES = [
    'php' => 'tugboatqa/php:8.3-apache',
    'mysql' => 'tugboatqa/mysql:8',
  ];

  public function __construct(
    // @todo what if all builds were a plugin – so D7, D8, D9, Umami, Commerce?
    private OneClickDemoPluginManager $oneClickDemoManager
  ) {
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
    ['php' => $image_name, 'mysql' => $mysql_image] = $this->images($parameters['major_version']);

    // Rename drupal to core so that it becomes drupal/core as a package name.
    // Have core version match the selected project version, as they user may
    // not have opened advanced options.
    if ($parameters['project_type'] === ProjectTypes::CORE) {
      $parameters['project'] = 'core';
      $parameters['drupal_core_version'] = $parameters['project_version'];
    }

    // @todo we could have different Config classes, but this is an easy start.
    $build_commands = [
      self::ENVIRONMENT,
      $this->getSetupCommands($parameters),
      $this->getDownloadCommands($parameters),
      ['echo "SIMPLYEST_STAGE_PATCHING"'],
      $this->getPatchingCommands($parameters),
    ];

    // composer-patches only writes the patcher's own output when Composer is
    // verbose. Without -v a rejected patch is reported as "No available patcher
    // was able to apply patch ..." and nothing else, so nobody can tell whether
    // the patch is stale, the depth is wrong, or the file no longer exists.
    $composer_update = $this->hasPatches($parameters)
      ? 'composer update --no-ansi -v'
      : 'composer update --no-ansi';

    if ($parameters['major_version'] > 8) {
      $build_commands[] = ['cd stm && ' . $composer_update];
    }
    else if ($parameters['major_version'] === 8) {
      $build_commands[] = ['cd "${DOCROOT}" && ' . $composer_update];
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
          'image' => $mysql_image,
        ],
      ],
    ];
  }

  /**
   * Where the Drupal project lives, in the base preview and the sandbox.
   */
  private const string PROJECT_DIR = '${TUGBOAT_ROOT}/stm';

  /**
   * Generates the config for a base preview.
   *
   * A base preview only runs the init stage. Sandboxes built on top of it
   * inherit that filesystem and run their own build commands, so anything here
   * is work every sandbox would otherwise repeat: PHP extensions, Apache
   * modules, tooling, and for each core release line a complete project at
   * its newest release.
   *
   * The project is the part that matters. Tugboat snapshots a sandbox after
   * its build, and that snapshot takes about as long as writing core and
   * vendor did, on top of the Composer time. A sandbox that finds the release
   * it wants already in place skips both.
   *
   * @param string $name
   *   The base preview name, as used by the launch code: `drupal10`, `umami`.
   *
   * @return array<string, mixed>
   *   The preview config.
   */
  public function basePreview(string $name): array {
    $major = self::majorVersionFromBaseName($name);
    $images = $major === NULL
      ? self::ONE_CLICK_DEMO_IMAGES
      : $this->images($major);
    if ($major === NULL) {
      $demo = $this->demoForBase($name);
      if ($demo === NULL) {
        throw new \InvalidArgumentException("No base preview is defined for '$name'.");
      }
    }

    // The image is bare here, so nothing needs to be checked first. Composer
    // is only ever updated here: a daily base is fresh enough, and it keeps
    // the sandbox build from paying for it.
    $init = [
      'docker-php-ext-install bcmath',
      'a2enmod headers rewrite',
      'wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq',
      'composer self-update',
      self::ALLOW_ADVISORIES,
    ];
    // Nothing about a demo depends on the launch, so its base is the whole
    // demo, installed. A launch clones it, which takes seconds. Drupal 7 and 8
    // sandboxes are git checkouts and get nothing here.
    if ($major === NULL) {
      $init = [...$init, ...$this->demoCommands($demo, [])];
    }
    elseif ($major > 8) {
      // The same steps a sandbox runs for itself, at the line's newest
      // release, so a sandbox asking for that release finds nothing to do.
      $init[] = 'rm -rf "${DOCROOT}"';
      $init[] = sprintf('cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/recommended-project:^%d stm --no-install', $major);
      $init[] = 'cd "' . self::PROJECT_DIR . '" && composer config minimum-stability dev';
      $init[] = 'cd "' . self::PROJECT_DIR . '" && composer config prefer-stable true';
      $init[] = 'cd "' . self::PROJECT_DIR . '" && composer require --no-install drush/drush';
      $init[] = 'cd "' . self::PROJECT_DIR . '" && composer update --no-ansi';
      $init[] = 'ln -snf "' . self::PROJECT_DIR . '/web" "${DOCROOT}"';
    }

    return [
      'services' => [
        'php' => [
          'image' => $images['php'],
          'default' => TRUE,
          'depends' => 'mysql',
          'commands' => [
            'init' => $init,
          ],
        ],
        'mysql' => [
          'image' => $images['mysql'],
        ],
      ],
    ];
  }

  /**
   * Reads the core major version out of a `drupalN` base preview name.
   */
  public static function majorVersionFromBaseName(string $name): ?int {
    if (preg_match('/^drupal(\d+)$/', $name, $matches) !== 1) {
      return NULL;
    }
    return (int) $matches[1];
  }

  /**
   * The Docker images for a core major version.
   *
   * @return array{php: string, mysql: string}
   */
  private function images(int $major_version): array {
    // @todo make these configurable in #3236528
    // @see https://www.drupal.org/project/simplytest/issues/
    $php = match($major_version) {
      7, 8 => 'tugboatqa/php:7.4-apache',
      9 => 'tugboatqa/php:8.1-apache',
      10 => 'tugboatqa/php:8.2-apache',
      // Defaults to the latest PHP version.
      default => 'tugboatqa/php:apache'
    };
    $mysql = $major_version > 10 ? 'tugboatqa/mysql:8' : 'tugboatqa/mysql:5';
    return ['php' => $php, 'mysql' => $mysql];
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

    return [
      'services' => [
        'php' => [
          'image' => self::ONE_CLICK_DEMO_IMAGES['php'],
          'default' => TRUE,
          'depends' => 'mysql',
          'commands' => [
            'build' => [...self::ENVIRONMENT, ...$this->demoCommands($one_click_demo, $parameters)],
          ],
        ],
        'mysql' => [
          'image' => self::ONE_CLICK_DEMO_IMAGES['mysql'],
        ],
      ],
    ];
  }

  /**
   * Everything that turns a bare environment into an installed demo.
   *
   * Shared between the demo's base preview, where it runs during init, and
   * the from-scratch build a launch falls back to when no base is usable.
   *
   * @param array<string, mixed> $parameters
   *
   * @return list<string>
   */
  private function demoCommands(OneClickDemoInterface $demo, array $parameters): array {
    // @todo all things should be build plugins, normalize with ::generate.
    return array_merge(
      $demo->getSetupCommands($parameters),
      ['echo "SIMPLYEST_STAGE_DOWNLOAD"'],
      $demo->getDownloadCommands($parameters),
      ['echo "SIMPLYEST_STAGE_PATCHING"'],
      $demo->getPatchingCommands($parameters),
      [
        'cd stm && composer update --no-ansi',
        'echo "SIMPLYEST_STAGE_INSTALLING"',
        'cd "${DOCROOT}" && chmod -R 777 sites/default',
      ],
      $demo->getInstallingCommands($parameters),
      [
        'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
        'chown -R www-data:www-data "${DOCROOT}"/sites/default/files',
        'echo "SIMPLYEST_STAGE_FINALIZE"',
      ],
    );
  }

  /**
   * The demo plugin whose base preview carries a name.
   */
  private function demoForBase(string $name): ?OneClickDemoInterface {
    foreach ($this->oneClickDemoManager->getDefinitions() as $id => $definition) {
      if ($definition['base_preview_name'] === $name) {
        $demo = $this->oneClickDemoManager->createInstance($id);
        assert($demo instanceof OneClickDemoInterface);
        return $demo;
      }
    }
    return NULL;
  }

  private function getSetupCommands(array $parameters) {
    $commands = [];
    if ($parameters['major_version'] > 8) {
      $version = $parameters['drupal_core_version'];
      // The base preview holds the line's newest release, already installed.
      // When that is the release asked for, the project is reused as is and
      // the sandbox only adds what the launch itself needs. Anything else,
      // including a dev release or no base at all, builds from scratch.
      $create = [
        'rm -rf "${DOCROOT}" "' . self::PROJECT_DIR . '"',
        sprintf('cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/recommended-project:%s stm --no-install', $version),
        'cd "' . self::PROJECT_DIR . '" && composer config minimum-stability dev',
        'cd "' . self::PROJECT_DIR . '" && composer config prefer-stable true',
        // The phpspec/prophecy-phpunit check was added in 9.1.6
        // @see https://www.drupal.org/i/3182653
        // @see https://git.drupalcode.org/project/drupal/-/commit/94d0c1f
        'cd "' . self::PROJECT_DIR . '" && composer require --no-install drush/drush',
        'ln -snf "' . self::PROJECT_DIR . '/web" "${DOCROOT}"',
      ];
      $installed = sprintf('$(cd "%s" 2>/dev/null && composer show drupal/core --format=json 2>/dev/null | jq -r \'.versions[0]\')', self::PROJECT_DIR);
      $commands[] = sprintf('[ "%s" = "%s" ] && echo "Reusing Drupal %2$s from the base preview" || (%s)', $installed, $version, implode(' && ', $create));
      // Pin core to the requested release either way, otherwise `composer
      // update` could bump it to whatever is newest.
      $commands[] = sprintf('cd "' . self::PROJECT_DIR . '" && composer require --dev --no-install drupal/core:%s', $version);
    }
    // Legacy non-composer build.
    else {
      $commands[] = 'cd "${DOCROOT}" && git config core.fileMode false';
      $commands[] = 'cd "${DOCROOT}" && git fetch --all';

      if (str_ends_with((string) $parameters['drupal_core_version'], '-dev')) {
        $commands[] = sprintf('cd "${DOCROOT}" && git reset --hard origin/' . substr((string) $parameters['drupal_core_version'], 0, -4));
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

    if ($parameters['major_version'] > 8) {
      $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-install', $parameters['project'], $this->getComposerCompatibleVersionString($parameters['project_version']));
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd stm && composer require drupal/%s:%s --no-install', $additional['shortname'], $this->getComposerCompatibleVersionString($additional['version']));
      }
    }
    else if ($parameters['major_version'] === 8) {
      $commands[] = 'cd "${DOCROOT}" && composer require zaporylie/composer-drupal-optimizations:^1.0 --no-install';
      $commands[] = 'cd "${DOCROOT}" && composer install --no-ansi';
      if (!$is_core) {
        $commands[] = sprintf('cd "${DOCROOT}" && composer require drupal/%s:%s --no-install', $parameters['project'], $this->getComposerCompatibleVersionString($parameters['project_version']));
      }
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('cd "${DOCROOT}" && composer require drupal/%s:%s --no-install', $additional['shortname'], $this->getComposerCompatibleVersionString($additional['version']));
      }
    }
    else if ($parameters['major_version'] === 7) {
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
    if (!$this->hasPatches($parameters)) {
      return [];
    }

    if ($parameters['major_version'] === 7) {
      return $this->getLegacyPatchingCommands($parameters);
    }

    $dir = $parameters['major_version'] === 8 ? '"${DOCROOT}"' : 'stm';
    // composer-patches is required into the build rather than installed
    // globally so the version is ours to choose. Composer installs plugins
    // ahead of everything else, so the patcher is active for the same
    // `composer update` that installs the packages it patches.
    return [
      sprintf('cd %s && composer config --no-interaction allow-plugins.cweagans/composer-patches true', $dir),
      sprintf('cd %s && composer config --no-interaction extra.composer-patches.patches-file patches.json', $dir),
      sprintf('cd %s && composer require --no-update %s', $dir, self::COMPOSER_PATCHES),
      sprintf('cd %s && echo %s > patches.json', $dir, escapeshellarg($this->getPatchesFile($parameters))),
    ];
  }

  /**
   * Builds the patches.json read by composer-patches.
   *
   * @param array<mixed> $parameters
   *   The preview config parameters.
   *
   * @return string
   *   The encoded patches file.
   */
  private function getPatchesFile(array $parameters): string {
    $patches = [];
    $package = 'drupal/' . $parameters['project'];
    foreach ($this->getSubmittedPatches($parameters['patches']) as $patch) {
      $patches[$package][] = $this->buildPatchDefinition($package, $patch);
    }
    foreach ($parameters['additionals'] as $additional) {
      $package = 'drupal/' . $additional['shortname'];
      foreach ($this->getSubmittedPatches($additional['patches']) as $additional_patch) {
        $patches[$package][] = $this->buildPatchDefinition($package, $additional_patch);
      }
    }
    return json_encode(['patches' => $patches], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }

  /**
   * Returns the patch URLs a project was actually given.
   *
   * The launch form renders a patch field for every project whether or not it
   * is used, so a submission carries an empty string for each project left
   * unpatched. Those must not reach the build: composer-patches takes an empty
   * URL at face value and fails the whole update with "$url must not be an
   * empty string".
   *
   * @param array<mixed> $patches
   *   The submitted patch values for one project.
   *
   * @return list<string>
   *   The patch URLs to apply.
   *
   * @see https://www.drupal.org/project/simplytest/issues/3348026
   */
  private function getSubmittedPatches(array $patches): array {
    $urls = [];
    foreach ($patches as $patch) {
      $patch = trim((string) $patch);
      if ($patch !== '') {
        $urls[] = $patch;
      }
    }
    return $urls;
  }

  /**
   * Builds a single composer-patches patch definition.
   *
   * The expanded definition format is used rather than the compact
   * `description: url` one because only the expanded format carries a depth and
   * per-patch patcher configuration.
   *
   * @param string $package
   *   The Composer package the patch applies to.
   * @param string $url
   *   The patch URL as submitted.
   *
   * @return array{description: string, url: string, depth: int, extra: array{freeform: array{executable: string, dry_run_args: string, args: string}}}
   *   The patch definition.
   */
  private function buildPatchDefinition(string $package, string $url): array {
    $url = $this->normalizePatchUrl($url);
    return [
      'description' => sprintf('STM patch %s', basename(parse_url($url, PHP_URL_PATH) ?: $url)),
      'url' => $url,
      // Patches for drupal/core are cut from the drupal/drupal monorepo, where
      // the files live under core/. That prefix is not part of the package.
      'depth' => $package === 'drupal/core' ? 2 : 1,
      'extra' => ['freeform' => self::FREEFORM_PATCHER],
    ];
  }

  /**
   * Rewrites a GitLab merge request `.patch` URL to its `.diff` equivalent.
   *
   * `N.patch` is the whole commit series as an mbox, so applying it replays
   * every commit in turn. Any file the series touches outside the patch depth
   * aborts the entire apply. For drupal/core that includes anything committed
   * to the repository root. `N.diff` is the same change squashed into one diff
   * against the merge base, which is what a sandbox wants.
   *
   * @param string $url
   *   The patch URL as submitted.
   *
   * @return string
   *   The URL to fetch the patch from.
   */
  private function normalizePatchUrl(string $url): string {
    return preg_replace('#(/-/merge_requests/\d+)\.patch($|\?)#', '$1.diff$2', $url) ?? $url;
  }

  /**
   * Determines whether the build has any patches to apply.
   *
   * @param array<mixed> $parameters
   *   The preview config parameters.
   *
   * @return bool
   *   TRUE if the project or any additional project has a patch.
   */
  private function hasPatches(array $parameters): bool {
    if ($this->getSubmittedPatches($parameters['patches']) !== []) {
      return TRUE;
    }
    foreach ($parameters['additionals'] as $additional) {
      if ($this->getSubmittedPatches($additional['patches']) !== []) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds the patching commands for Drupal 7, which has no Composer build.
   *
   * @param array<mixed> $parameters
   *   The preview config parameters.
   *
   * @return list<string>
   *   The build commands.
   */
  private function getLegacyPatchingCommands(array $parameters): array {
    $commands = [];
    // Patch Drupal 7 to automatically redirect to the installer.
    if ($parameters['perform_install'] === FALSE) {
      $commands[] = $this->getLegacyPatchCommand(ProjectTypes::CORE, '', 'https://www.drupal.org/files/issues/2019-12-19/3077423-11.patch');
    }
    foreach ($this->getSubmittedPatches($parameters['patches']) as $patch) {
      $commands[] = $this->getLegacyPatchCommand($parameters['project_type'], $parameters['project'], $patch);
    }
    foreach ($parameters['additionals'] as $additional) {
      foreach ($this->getSubmittedPatches($additional['patches']) as $additional_patch) {
        $commands[] = $this->getLegacyPatchCommand($additional['type'], $additional['shortname'], $additional_patch);
      }
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
      if ($parameters['major_version'] > 7) {
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

    if ($parameters['major_version'] > 8) {
      $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush si %s --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y', $install_profile);
      // Enable verbose error reporting.
      $commands[] = 'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y';
      if ($parameters['project_type'] === ProjectTypes::MODULE) {
        $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush en %s -y', $parameters['project']);
      }

      if ($parameters['project_type'] === ProjectTypes::THEME) {
        $commands[] = sprintf('deps=$(yq -o=json \'.dependencies // []\' ${DOCROOT}/themes/contrib/%1$s/%1$s.info.yml | jq -r \'.[] | split(":")[1]\' | xargs); [ -z "$deps" ] || ${DOCROOT}/../vendor/bin/drush en $deps -y', $parameters['project']);
        $commands[] = sprintf('cd "${DOCROOT}" && ../vendor/bin/drush theme:enable %s -y', $parameters['project']);
        $commands[] = sprintf(
          'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme %s %s -y',
          $parameters['project'] === 'gin' ? 'admin' : 'default',
          $parameters['project']
        );
      }
      foreach ($parameters['additionals'] as $additional) {
        $additional_product_type = $additional['project_type'] ?? ProjectTypes::MODULE;
        if ($additional_product_type === ProjectTypes::THEME) {
          $commands[] = sprintf('deps=$(yq -o=json \'.dependencies // []\' ${DOCROOT}/themes/contrib/%1$s/%1$s.info.yml | jq -r \'.[] | split(":")[1]\' | xargs); [ -z "$deps" ] || ${DOCROOT}/../vendor/bin/drush en $deps -y', $additional['shortname']);
        }
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
    else {
      $commands[] = sprintf('drush -r "${DOCROOT}" si %s --account-name=admin --account-pass=admin -y', $install_profile);
      if ($parameters['major_version'] === 8) {
        $commands[] = 'drush -r "${DOCROOT}" config-set system.logging error_level verbose -y';
      }
      if (!$is_distro && !$is_core) {
        $commands[] = sprintf('drush -r "${DOCROOT}" en %s -y', $parameters['project']);
      }
      foreach ($parameters['additionals'] as $additional) {
        $commands[] = sprintf('drush -r "${DOCROOT}" en %s -y', $additional['shortname']);
      }
    }

    if ($parameters['major_version'] === 7) {
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
    if ($result === 1) {
      return $legacy_matches[1];
    }
    return $version;
  }

}
