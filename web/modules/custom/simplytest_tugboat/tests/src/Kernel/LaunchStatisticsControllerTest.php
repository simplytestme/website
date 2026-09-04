<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_tugboat\Controller\LaunchStatisticsController;
use Drupal\simplytest_tugboat\LaunchRecorder;

/**
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\Controller\LaunchStatisticsController
 */
final class LaunchStatisticsControllerTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_ocd',
    'simplytest_projects',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installSchema('simplytest_tugboat', LaunchRecorder::TABLE_NAME);
  }

  /**
   * @covers ::report
   */
  public function testReportWithNoLaunches(): void {
    $output = $this->renderReport();

    self::assertStringContainsString('Launch statistics', $output);
    self::assertStringContainsString('No launches recorded yet', $output);
  }

  /**
   * @covers ::report
   * @covers ::peak
   */
  public function testReportListsWhatWasLaunched(): void {
    $this->record('token');
    $this->record('token');
    $this->record('webform');

    $output = $this->renderReport();

    self::assertStringNotContainsString('No launches recorded yet', $output);
    self::assertStringContainsString('https://www.drupal.org/project/token', $output);
    self::assertStringContainsString('https://www.drupal.org/project/webform', $output);
    self::assertStringContainsString('Recording since', $output);
  }

  /**
   * Demos are listed by title, and a demo that no longer exists by its ID.
   *
   * @covers ::report
   * @covers ::labelDemos
   */
  public function testReportListsOneClickDemosByTitle(): void {
    $this->recordDemo('starshot');
    $this->recordDemo('starshot');
    $this->recordDemo('oneclickdemo_retired');

    $output = $this->renderReport();

    self::assertStringContainsString('Drupal CMS', $output);
    self::assertStringNotContainsString('starshot', $output);
    self::assertStringContainsString('oneclickdemo_retired', $output);
    self::assertStringContainsString('Nothing launched in this window.', $output);
  }

  /**
   * Failed launches stay out of the public numbers.
   *
   * @covers ::report
   */
  public function testReportExcludesFailedLaunches(): void {
    $this->record('token', LaunchRecorder::STATUS_FAILED);

    self::assertStringContainsString('No launches recorded yet', $this->renderReport());
  }

  /**
   * The report is invalidated by launches and otherwise kept for a day.
   *
   * The Expires header carries the same lifetime because the anonymous page
   * cache ignores a render array's max-age and only reads that header.
   *
   * @covers ::report
   */
  public function testReportCacheability(): void {
    $now = $this->container->get('datetime.time')->getRequestTime();
    $build = LaunchStatisticsController::create($this->container)->report();

    self::assertEquals([LaunchRecorder::CACHE_TAG], $build['#cache']['tags']);
    self::assertEquals(86400, $build['#cache']['max-age']);
    self::assertEquals(
      [['Expires', gmdate('D, d M Y H:i:s', $now + 86400) . ' GMT']],
      $build['#attached']['http_header']
    );
  }

  private function renderReport(): string {
    $build = LaunchStatisticsController::create($this->container)->report();
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  private function record(string $project, string $status = LaunchRecorder::STATUS_LAUNCHED): void {
    $this->insert([
      'project' => $project,
      'project_type' => ProjectTypes::MODULE,
      'project_version' => '8.x-1.0',
      'core_version' => '10.3.0',
      'install_profile' => 'standard',
      'one_click_demo' => '',
    ], $status);
  }

  private function recordDemo(string $plugin_id): void {
    $this->insert([
      'project' => '',
      'project_type' => '',
      'project_version' => '',
      'core_version' => '',
      'install_profile' => '',
      'one_click_demo' => $plugin_id,
    ], LaunchRecorder::STATUS_LAUNCHED);
  }

  /**
   * @param array<string, string> $fields
   */
  private function insert(array $fields, string $status): void {
    $created = $this->container->get('datetime.time')->getRequestTime();
    $this->container->get('database')->insert(LaunchRecorder::TABLE_NAME)
      ->fields($fields + [
        'created' => $created,
        'created_date' => gmdate('Y-m-d', $created),
        'status' => $status,
        'preview_id' => 'preview-abc',
        'manual_install' => 0,
        'patch_count' => 0,
        'additional_count' => 0,
      ])
      ->execute();
  }

}
