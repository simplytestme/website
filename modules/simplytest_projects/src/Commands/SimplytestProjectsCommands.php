<?php

namespace Drupal\simplytest_projects\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class SimplytestProjectsCommands extends DrushCommands {

  private $coreVersionManager;
  private $projectVersionManager;

  public function __construct(CoreVersionManager $core_version_manager, ProjectVersionManager $project_version_manager) {
    parent::__construct();
    $this->coreVersionManager = $core_version_manager;
    $this->projectVersionManager = $project_version_manager;
  }

  /**
   * Fetches versions for a major version of Drupal core.
   *
   * @param string $version
   *   Argument description.
   *
   * @command simplytest:projects:core-versions-update
   */
  public function updateData(string $version) {
    $this->coreVersionManager->updateData((int) $version);
  }

  /**
   * Gets release data for a project
   *
   * @param string $short_name
   *
   * @command simplytest:projects:get-release-data
   */
  public function getReleaseData(string $short_name) {
    $this->projectVersionManager->updateData($short_name);
  }
}
