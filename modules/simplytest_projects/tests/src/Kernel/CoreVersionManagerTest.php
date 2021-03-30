<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\CoreVersionManager
 */
final class CoreVersionManagerTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects'
  ];

  /**
   * @var \Drupal\simplytest_projects\CoreVersionManager
   */
  private $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.core_version_manager');
  }

  /**
   * @dataProvider coreVersionData
   * @covers ::updateData
   * @covers ::getVersions
   *
   * @param int $major_version
   *   The test major version.
   * @param int $expected_count
   *   The expected release count.
   * @param array $expected_result_sample
   *   The expected release data sample.
   *
   * @throws \Exception
   */
  public function testReleaseData(int $major_version, int $expected_count, array $expected_result_sample): void {
    $this->sut->updateData($major_version);
    $results = $this->sut->getVersions($major_version);
    $this->assertCount($expected_count, $results);
    $this->assertEquals($expected_result_sample, (array) $results[0]);
  }

  public function coreVersionData(): \Generator {
    yield [9, 32, [
      'version' => '9.2.x-dev',
      'major' => '9',
      'minor' => '2',
      'patch' => null,
      'extra' => 'dev',
      'vcs_label' => '9.2.x',
      'insecure' => '0',
    ]];
    yield [8, 200, [
      'version' => '8.9.9',
      'major' => '8',
      'minor' => '9',
      'patch' => '9',
      'extra' => null,
      'vcs_label' => '8.9.9',
      'insecure' => '1',
    ]];
    yield [7, 89, [
      'version' => '7.9',
      'major' => '7',
      'minor' => '9',
      'patch' => null,
      'extra' => null,
      'vcs_label' => '7.9',
      'insecure' => '1',
    ]];
    yield [10, 1, [
      'version' => '10.0.x-dev',
      'major' => '10',
      'minor' => '0',
      'patch' => NULL,
      'extra' => 'dev',
      'vcs_label' => '10.0.x',
      'insecure' => '0',
    ]];
  }

  public function testGetWithCompatibility() {
    $this->sut->updateData(7);
    $this->sut->updateData(8);
    $this->sut->updateData(9);

    $this->assertCount(89, $this->sut->getWithCompatibility('7.x'));
    $this->assertCount(0, $this->sut->getWithCompatibility('^10.0'));
    $this->assertCount(32, $this->sut->getWithCompatibility('^9'));
    $this->assertCount(14, $this->sut->getWithCompatibility('^8.9.1'));
    $this->assertCount(200, $this->sut->getWithCompatibility('8.x'));
  }

}
