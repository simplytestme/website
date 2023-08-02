<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\Exception\EntityValidationException;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_projects
 */
final class ProjectPreSaveValidationTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects'
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
  }

  public function testValidationExceptionOnPreSave() {
    $project = SimplytestProject::create([
      'title' => 'Pathauto',
      'shortname' => 'pathauto',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
      'creator' => 'Dave Reid',
    ]);
    $project->save();

    $this->expectException(EntityValidationException::class);
    $this->expectExceptionMessage('[simplytest_project]: shortname=A simplytest project with Shortname <em class="placeholder">pathauto</em> already exists.');

    try {
      $project = SimplytestProject::create([
        'title' => 'Pathauto',
        'shortname' => 'pathauto',
        'sandbox' => "0",
        'type' => ProjectTypes::MODULE,
        'creator' => 'Dave Reid',
      ]);
      $project->save();
    }
    catch (EntityStorageException $e) {
      throw $e->getPrevious();
    }

  }

}
