<?php

namespace Drupal\simplytest_projects\Commands;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\simplytest_projects\ProjectImporter;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects\ProjectFetcher;
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

  private CoreVersionManager $coreVersionManager;
  private ProjectVersionManager $projectVersionManager;
  private ProjectFetcher $projectFetcher;
  private ProjectImporter $projectImporter;

  public function __construct(
    CoreVersionManager $core_version_manager,
    ProjectVersionManager $project_version_manager,
    ProjectFetcher $project_fetcher,
    ProjectImporter $project_importer
  ) {
    parent::__construct();
    $this->coreVersionManager = $core_version_manager;
    $this->projectVersionManager = $project_version_manager;
    $this->projectFetcher = $project_fetcher;
    $this->projectImporter = $project_importer;
  }

  /**
   * Fetches versions for a major version of Drupal core.
   *
   * @param string $version
   *   Argument description.
   *
   * @command simplytest:projects:core-versions-update
   */
  public function coreVersionsUpdate(string $version) {
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

  /**
   * Gets release data for a project
   *
   * @param string $short_name
   *
   * @command simplytest:projects:import-project
   *
   * @throws \Exception
   */
  public function importProject(string $short_name) {
    try {
      $this->projectFetcher->fetchProject($short_name);
      $this->projectVersionManager->updateData($short_name);
    }
    catch (EntityStorageException $exception) {
      $previous = $exception->getPrevious();
      assert($previous !== NULL);
      $this->io()->error($previous->getMessage());
    }
  }

  /**
   * Batch import a type.
   *
   * @param string $type
   *   module, theme, or distribution.
   * @command simplytest:projects:import
   */
  public function importType(string $type) {
    $batch_builder = $this->projectImporter->buildBatch($type);
    batch_set($batch_builder->toArray());
    drush_backend_batch_process();
  }

}
