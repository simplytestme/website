<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\Commands\SimplytestProjectsCommands;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\Commands\SimplytestProjectsCommands
 */
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

  /**
   * @covers ::coreVersionsUpdate
   */
  public function testCoreVersionsUpdate(): void {
    $this->sut->coreVersionsUpdate('9');

    self::assertNotEmpty(
      $this->container->get('simplytest_projects.core_version_manager')->getVersions(9),
    );
  }

  /**
   * @covers ::getReleaseData
   */
  public function testGetReleaseData(): void {
    $this->sut->getReleaseData('token');

    self::assertNotEmpty(
      $this->container->get('simplytest_projects.project_version_manager')->getAllReleases('token'),
    );
  }

  /**
   * @covers ::importProject
   */
  public function testImportProject(): void {
    $this->sut->importProject('token');

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    self::assertCount(1, $storage->loadByProperties(['shortname' => 'token']));
    self::assertNotEmpty(
      $this->container->get('simplytest_projects.project_version_manager')->getAllReleases('token'),
    );
  }

}
