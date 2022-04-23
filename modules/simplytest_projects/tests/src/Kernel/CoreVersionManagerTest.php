<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\State\State;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use GuzzleHttp\Client;

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

  public function testPreflightCheck() {
    $state = new State(new KeyValueMemoryFactory());
    $sut = new CoreVersionManager(
      $this->container->get('database'),
      new Client(),
      $state
    );
    $sut->updateData(7);
    self::assertNotNull($state->get('release_history_last_modified:drupal:7'));
    $last_modified = $state->get('release_history_last_modified:drupal:7');
    $sut->updateData(7);
    self::assertEquals($last_modified, $state->get('release_history_last_modified:drupal:7'));
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
    $this->assertGreaterThanOrEqual($expected_count, $results);
    // NOTE: We do the array map because assertContains performs a strict check
    // and strict checks against objects always fail if they are not literally
    // the same object.
    $this->assertContains($expected_result_sample, array_map(static function(object $result) {
      return (array) $result;
    }, $results));
  }

  public function coreVersionData(): \Generator {
    yield [9, 33, [
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
    yield [7, 90, [
      'version' => '7.9',
      'major' => '7',
      'minor' => '9',
      'patch' => null,
      'extra' => null,
      'vcs_label' => '7.9',
      'insecure' => '1',
    ]];
    yield [10, 3, [
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

    $this->assertGreaterThanOrEqual(90, $this->sut->getWithCompatibility('7.x'));
    $this->assertCount(0, $this->sut->getWithCompatibility('^10.0'));
    $this->assertGreaterThanOrEqual(33, $this->sut->getWithCompatibility('^9'));
    $this->assertGreaterThanOrEqual(14, $this->sut->getWithCompatibility('^8.9.1'));
    $this->assertGreaterThanOrEqual(200, $this->sut->getWithCompatibility('8.x'));
  }

}
