<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_projects
 */
final class ProjectInsertVersionsUpdatedTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects'
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
  }

  public function testVersionsExistAfterInsert() {
    $project = SimplytestProject::create([
      'title' => 'Pathauto',
      'shortname' => 'pathauto',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
      'creator' => 'Dave Reid',
    ]);
    $project->save();

    $project_version_manager = $this->container->get('simplytest_projects.project_version_manager');
    $releases = $project_version_manager->getAllReleases($project->getShortname());
    $this->assertNotEmpty($releases);
  }

}
