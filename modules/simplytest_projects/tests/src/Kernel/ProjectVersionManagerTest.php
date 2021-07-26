<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use Drupal\Tests\simplytest_projects\Traits\MockedReleaseHttpClientTrait;
use GuzzleHttp\Client;

/**
 * @coversDefaultClass \Drupal\simplytest_projects\ProjectVersionManager
 */
final class ProjectVersionManagerTest extends KernelTestBase {

  use MockedReleaseHttpClientTrait;

  protected static $modules = [
    'simplytest_projects'
  ];

  /**
   * @var \Drupal\simplytest_projects\ProjectVersionManager
   */
  private $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = new ProjectVersionManager(
      $this->container->get('database'),
      new Fetcher(
        $this->getMockedHttpClient(),
        $this->container->get('state')
      )
    );
  }

  /**
   * @covers ::updateData
   */
  public function testUpdateData(): void {
    $this->sut->updateData('pathauto');
    $database = $this->container->get('database');

    $query = $database->select(ProjectVersionManager::TABLE_NAME);
    assert($query instanceof SelectInterface);
    $query
      ->fields(ProjectVersionManager::TABLE_NAME)
      ->condition('short_name', 'pathauto');
    $count = $query->countQuery()->execute()->fetchField();
    self::assertEquals(28, $count);
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
}
