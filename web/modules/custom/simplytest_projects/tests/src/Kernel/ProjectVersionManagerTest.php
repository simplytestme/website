<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\Tests\simplytest_projects\Traits\MockedReleaseHttpClientTrait;

/**
 * @coversDefaultClass \Drupal\simplytest_projects\ProjectVersionManager
 */
final class ProjectVersionManagerTest extends KernelTestBase {

  use MockedReleaseHttpClientTrait;

  protected static $modules = [
    'simplytest_projects',
  ];

  private ProjectVersionManager $sut;

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    self::registerWithContainer($container, $this);
  }

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.project_version_manager');
  }

  /**
   * @covers ::updateData
   */
  public function testUpdateData(): void {
    $this->sut->updateData('pathauto');
    $database = $this->container->get('database');

    $query = $database->select(ProjectVersionManager::TABLE_NAME);
    $query
      ->fields(ProjectVersionManager::TABLE_NAME)
      ->condition('short_name', 'pathauto');
    $count = $query->countQuery()->execute()->fetchField();
    self::assertEquals(28, $count);
  }

  /**
   * @see https://www.drupal.org/project/simplytest/issues/3208252
   */
  public function testInvalidIntegerValue(): void {
    $this->sut->updateData('drupalbin');
    $database = $this->container->get('database');

    $query = $database->select(ProjectVersionManager::TABLE_NAME);
    $query
      ->fields(ProjectVersionManager::TABLE_NAME)
      ->condition('short_name', 'drupalbin');
    $count = $query->countQuery()->execute()->fetchField();
    self::assertEquals(1, $count);
  }

  /**
   * @covers ::getAllReleases
   */
  public function testGetAllReleases(): void {
    $this->sut->updateData('pathauto');
    $releases = $this->sut->getAllReleases('pathauto');
    self::assertCount(28, $releases);
  }

  public function testOrganizeAndSortReleases(): void {
    $this->sut->updateData('pathauto');
    $releases = $this->sut->getAllReleases('pathauto');
    $organized = $this->sut->organizeAndSortReleases($releases);
    self::assertArrayHasKey('latest', $organized);
    // @todo we have 3 due to core compatibility, aka majors.
    self::assertCount(3, $organized['latest']);
    self::assertArrayHasKey('branches', $organized);
    self::assertCount(2, $organized['branches']);
    self::assertArrayHasKey('core', $organized);
    self::assertCount(3, $organized['core']);
  }

  public function testBootstrap3523792(): void {
    $this->sut->updateData('bootstrap');
    $releases = $this->sut->getAllReleases('bootstrap');
    $organized = $this->sut->organizeAndSortReleases($releases);
    $latest = $organized['latest'];
    self::assertCount(5, $latest);
    self::assertEquals([
      '5.0.2',
      '8.x-3.38',
      '8.x-3.32',
      '8.x-3.24',
      '8.x-3.23',
    ], array_map(
      static fn($release) => $release->version,
      $latest
    ));
  }

}
