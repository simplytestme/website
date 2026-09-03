<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Twig\Error\RuntimeError;

/**
 * The report component enforces the shape of the data it is handed.
 *
 * Schemas are mandatory for components provided by modules, and props are
 * validated inside an assert(), so these expectations hold in tests and in
 * development and are compiled out in production.
 *
 * Twig wraps the validation failure in a RuntimeError, so the assertions below
 * match on the message rather than the inner exception class. The message is
 * what a developer actually sees, and it names the offending prop path.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class LaunchStatisticsComponentTest extends KernelTestBase {

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
  }

  /**
   * The component is discovered from the module without any module enabled.
   */
  public function testComponentIsDiscovered(): void {
    $component = $this->container->get('plugin.manager.sdc')
      ->find('simplytest_tugboat:launch-statistics');

    self::assertEquals('simplytest_tugboat', $component->getPluginDefinition()['provider']);
  }

  /**
   * Valid props render.
   */
  public function testValidPropsRender(): void {
    $build = $this->build();
    $output = (string) $this->container->get('renderer')->renderRoot($build);

    self::assertStringContainsString('Launch statistics', $output);
    self::assertStringContainsString('https://www.drupal.org/project/token', $output);
  }

  /**
   * A daily entry missing its total is rejected rather than rendered blank.
   */
  public function testDailyEntryMissingTotalIsRejected(): void {
    $build = $this->build();
    $build['#props']['daily'] = [['date' => '2026-09-02']];

    $this->expectException(RuntimeError::class);
    $this->expectExceptionMessage('[daily[0].total] The property total is required');
    $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * A tally with a string count is rejected.
   */
  public function testTallyWithNonIntegerTotalIsRejected(): void {
    $build = $this->build();
    $build['#props']['projects'] = [['name' => 'token', 'total' => 'three']];

    $this->expectException(RuntimeError::class);
    $this->expectExceptionMessage('[projects[0].total] String value found, but an integer is required');
    $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * A missing required prop is rejected.
   */
  public function testMissingRequiredPropIsRejected(): void {
    $build = $this->build();
    unset($build['#props']['totals']);

    $this->expectException(RuntimeError::class);
    $this->expectExceptionMessage('[totals] The property totals is required');
    $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * A site with nothing recorded yet passes a null first_recorded_at.
   */
  public function testNullFirstRecordedAtIsAllowed(): void {
    $build = $this->build();
    $build['#props']['first_recorded_at'] = NULL;
    $build['#props']['totals'] = ['week' => 0, 'window' => 0, 'all_time' => 0];

    $output = (string) $this->container->get('renderer')->renderRoot($build);
    self::assertStringContainsString('No launches recorded yet', $output);
  }

  /**
   * @return array<string, mixed>
   */
  private function build(): array {
    return [
      '#type' => 'component',
      '#component' => 'simplytest_tugboat:launch-statistics',
      '#props' => [
        'window_days' => 30,
        'totals' => ['week' => 3, 'window' => 12, 'all_time' => 40],
        'daily' => [
          ['date' => '2026-09-01', 'total' => 4],
          ['date' => '2026-09-02', 'total' => 8],
        ],
        'daily_peak' => 8,
        'projects' => [['name' => 'token', 'total' => 12]],
        'core_versions' => [['name' => '10.3.0', 'total' => 12]],
        'install_profiles' => [['name' => 'standard', 'total' => 12]],
        'project_types' => [['name' => 'Module', 'total' => 12]],
        'first_recorded_at' => 1756771200,
      ],
    ];
  }

}
