<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_tugboat\LaunchRecord;
use Drupal\simplytest_tugboat\LaunchRecorder;

/**
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\LaunchRecorder
 */
final class LaunchRecorderTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_ocd',
    'simplytest_projects',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_tugboat', LaunchRecorder::TABLE_NAME);
  }

  /**
   * @covers ::recordLaunch
   * @covers ::write
   * @covers \Drupal\simplytest_tugboat\LaunchRecord::fromPreviewParameters
   */
  public function testRecordLaunch(): void {
    $this->recorder()->recordLaunch(
      LaunchRecord::fromPreviewParameters($this->previewParameters()),
      'preview-abc'
    );

    $row = $this->loadOnlyRow();
    self::assertEquals(LaunchRecorder::STATUS_LAUNCHED, $row->status);
    self::assertEquals('preview-abc', $row->preview_id);
    self::assertEquals('token', $row->project);
    self::assertEquals(ProjectTypes::MODULE, $row->project_type);
    self::assertEquals('8.x-1.9', $row->project_version);
    self::assertEquals('9.3.2', $row->core_version);
    self::assertEquals('umami', $row->install_profile);
    self::assertEquals('', $row->one_click_demo);
    self::assertEquals(0, (int) $row->manual_install);
    self::assertEquals(2, (int) $row->patch_count);
    self::assertEquals(1, (int) $row->additional_count);
  }

  /**
   * The stored day matches the stored timestamp, in UTC.
   *
   * @covers ::recordLaunch
   * @covers ::write
   */
  public function testRecordLaunchStoresMatchingDay(): void {
    $this->recorder()->recordLaunch(
      LaunchRecord::fromPreviewParameters($this->previewParameters()),
      'preview-abc'
    );

    $row = $this->loadOnlyRow();
    self::assertEquals(gmdate('Y-m-d', (int) $row->created), $row->created_date);
  }

  /**
   * A manual install is stored as such.
   *
   * @covers \Drupal\simplytest_tugboat\LaunchRecord::fromPreviewParameters
   */
  public function testRecordLaunchWithManualInstall(): void {
    $parameters = $this->previewParameters();
    $parameters['perform_install'] = FALSE;
    $this->recorder()->recordLaunch(LaunchRecord::fromPreviewParameters($parameters), 'preview-abc');

    self::assertEquals(1, (int) $this->loadOnlyRow()->manual_install);
  }

  /**
   * A one click demo records which demo ran and nothing else.
   *
   * @covers ::recordLaunch
   * @covers ::write
   * @covers \Drupal\simplytest_tugboat\LaunchRecord::forOneClickDemo
   */
  public function testRecordOneClickDemo(): void {
    $this->recorder()->recordLaunch(LaunchRecord::forOneClickDemo('umami'), 'preview-ocd');

    $row = $this->loadOnlyRow();
    self::assertEquals('umami', $row->one_click_demo);
    self::assertEquals('', $row->project);
    self::assertEquals('', $row->core_version);
    self::assertEquals('preview-ocd', $row->preview_id);
  }

  /**
   * A failed launch is recorded without a preview.
   *
   * @covers ::recordFailure
   * @covers ::write
   */
  public function testRecordFailure(): void {
    $this->recorder()->recordFailure(LaunchRecord::fromPreviewParameters($this->previewParameters()));

    $row = $this->loadOnlyRow();
    self::assertEquals(LaunchRecorder::STATUS_FAILED, $row->status);
    self::assertEquals('', $row->preview_id);
    self::assertEquals('token', $row->project);
  }

  private function recorder(): LaunchRecorder {
    return $this->container->get('simplytest_tugboat.launch_recorder');
  }

  private function loadOnlyRow(): \stdClass {
    $rows = $this->container->get('database')
      ->select(LaunchRecorder::TABLE_NAME, 'r')
      ->fields('r')
      ->execute()
      ->fetchAll();
    self::assertCount(1, $rows);
    return $rows[0];
  }

  /**
   * @return array<string, mixed>
   */
  private function previewParameters(): array {
    return [
      'perform_install' => TRUE,
      'install_profile' => 'umami',
      'drupal_core_version' => '9.3.2',
      'project_type' => ProjectTypes::MODULE,
      'project_version' => '8.x-1.9',
      'project' => 'token',
      'patches' => ['one.patch', 'two.patch'],
      'additionals' => [['shortname' => 'ctools']],
    ];
  }

}
