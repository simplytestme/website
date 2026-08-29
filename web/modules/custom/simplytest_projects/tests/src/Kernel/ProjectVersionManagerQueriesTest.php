<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * Covers the read side of the project version manager.
 *
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ProjectVersionManager
 */
final class ProjectVersionManagerQueriesTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  private ProjectVersionManager $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.project_version_manager');
    $this->sut->updateData('token');
  }

  /**
   * @covers ::getRelease
   */
  public function testGetRelease(): void {
    $release = $this->sut->getRelease('token', '8.x-1.9');

    self::assertNotNull($release);
    self::assertEquals('token', $release['short_name']);
    self::assertEquals('8.x-1.9', $release['version']);
    self::assertArrayHasKey('core_compatibility', $release);
  }

  /**
   * A branch is looked up as its matching dev release.
   *
   * @covers ::getRelease
   */
  public function testGetReleaseExpandsBranchToDev(): void {
    $branch = $this->sut->getRelease('token', '8.x-1.x');
    $dev = $this->sut->getRelease('token', '8.x-1.x-dev');

    self::assertNotNull($branch);
    self::assertEquals($dev, $branch);
  }

  /**
   * @covers ::getRelease
   */
  public function testGetReleaseReturnsNullWhenMissing(): void {
    self::assertNull($this->sut->getRelease('token', '8.x-99.0'));
    self::assertNull($this->sut->getRelease('notaproject', '1.0.0'));
  }

  /**
   * @covers ::getAllReleases
   */
  public function testGetAllReleasesIsSortedNewestFirst(): void {
    $releases = $this->sut->getAllReleases('token');

    self::assertNotEmpty($releases);
    $dates = array_map(static fn(\stdClass $row) => (int) $row->date, $releases);
    $sorted = $dates;
    rsort($sorted);
    self::assertEquals($sorted, $dates);
  }

  /**
   * @covers ::getCompatibleReleases
   */
  public function testGetCompatibleReleases(): void {
    $compatible = $this->sut->getCompatibleReleases('token', '9.5.0');
    self::assertNotEmpty($compatible);

    $all = $this->sut->getAllReleases('token');
    self::assertLessThan(count($all), count($compatible));

    // Nothing in the result set excludes Drupal 9.
    foreach ($compatible as $release) {
      self::assertTrue(\Composer\Semver\Semver::satisfies('9.5.0', $release->core_compatibility));
    }
  }

  /**
   * @covers ::organizeAndSortReleases
   */
  public function testOrganizeAndSortReleasesWithNoReleases(): void {
    self::assertEquals(
      ['latest' => [], 'branches' => [], 'core' => []],
      $this->sut->organizeAndSortReleases([]),
    );
  }

  /**
   * A release whose compatibility is not a valid constraint is skipped.
   *
   * @covers ::organizeAndSortReleases
   */
  public function testOrganizeAndSortReleasesSkipsUnparseableCompatibility(): void {
    $releases = [
      (object) [
        'version' => '1.0.0',
        'core_compatibility' => 'not a constraint',
        'date' => '1600000000',
      ],
      (object) [
        'version' => '2.0.0',
        'core_compatibility' => '^10',
        'date' => '1600000001',
      ],
    ];

    $organized = $this->sut->organizeAndSortReleases($releases);

    $labels = array_column($organized['core'], 'label');
    self::assertEquals(['Drupal 10'], $labels);
    self::assertEquals(['2.0.0'], array_map(
      static fn(\stdClass $release) => $release->version,
      $organized['latest'],
    ));
  }

  /**
   * Dev branches are grouped separately from tagged releases.
   *
   * @covers ::organizeAndSortReleases
   */
  public function testOrganizeAndSortReleasesSeparatesBranches(): void {
    $organized = $this->sut->organizeAndSortReleases($this->sut->getAllReleases('token'));

    foreach ($organized['branches'] as $branch) {
      self::assertStringContainsString('-dev', $branch->version);
    }
    foreach ($organized['latest'] as $release) {
      self::assertStringNotContainsString('-dev', $release->version);
    }
  }

}
