<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\Commands\SimplytestProjectsCommands;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversClass(SimplytestProjectsCommands::class)]
#[CoversMethod(SimplytestProjectsCommands::class, 'coreVersionsUpdate')]
#[CoversMethod(SimplytestProjectsCommands::class, 'getReleaseData')]
#[CoversMethod(SimplytestProjectsCommands::class, 'importProject')]
#[Group('simplytest')]
#[Group('simplytest_project')]
#[RunTestsInSeparateProcesses]
final class ProjectCommandsTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  private SimplytestProjectsCommands $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);

    $this->sut = new SimplytestProjectsCommands(
      $this->container->get('simplytest_projects.core_version_manager'),
      $this->container->get('simplytest_projects.project_version_manager'),
      $this->container->get('simplytest_projects.fetcher'),
      $this->container->get('simplytest_projects.importer'),
      $this->container->get('simplytest_projects.seeder'),
    );
  }

  public function testCoreVersionsUpdate(): void {
    $this->sut->coreVersionsUpdate('9');

    self::assertNotEmpty(
      $this->container->get('simplytest_projects.core_version_manager')->getVersions(9),
    );
  }

  public function testGetReleaseData(): void {
    $this->sut->getReleaseData('token');

    self::assertNotEmpty(
      $this->container->get('simplytest_projects.project_version_manager')->getAllReleases('token'),
    );
  }

  public function testImportProject(): void {
    $this->sut->importProject('token');

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    self::assertCount(1, $storage->loadByProperties(['shortname' => 'token']));
    self::assertNotEmpty(
      $this->container->get('simplytest_projects.project_version_manager')->getAllReleases('token'),
    );
  }

}
