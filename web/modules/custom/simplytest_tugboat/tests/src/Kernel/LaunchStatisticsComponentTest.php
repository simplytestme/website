<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\Core\Plugin\Component;
use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\KernelTests\KernelTestBase;

/**
 * The report component enforces the shape of the data it is handed.
 *
 * Schemas are mandatory for components provided by modules. During rendering
 * the props are validated inside an assert(), which means the check is only
 * live where zend.assertions is on: locally yes, in CI and production no.
 *
 * So the rejection cases call the validator directly rather than rendering.
 * Going through the render pipeline would only prove the schema on a developer
 * machine, and would pass vacuously everywhere else.
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
    self::assertEquals(
      'simplytest_tugboat',
      $this->component()->getPluginDefinition()['provider']
    );
  }

  /**
   * Valid props render.
   */
  public function testValidPropsRender(): void {
    $build = $this->build();
    $output = (string) $this->container->get('renderer')->renderRoot($build);

    self::assertStringContainsString('Launch statistics', $output);
    self::assertStringContainsString('Drupal CMS', $output);
    self::assertStringContainsString('https://www.drupal.org/project/token', $output);
  }

  /**
   * Valid props pass validation.
   */
  public function testValidPropsValidate(): void {
    self::assertTrue($this->validate($this->build()['#props']));
  }

  /**
   * A daily entry missing its total is rejected rather than rendered blank.
   */
  public function testDailyEntryMissingTotalIsRejected(): void {
    $props = $this->build()['#props'];
    $props['daily'] = [['date' => '2026-09-02']];

    $this->expectException(InvalidComponentException::class);
    $this->expectExceptionMessage('[daily[0].total] The property total is required');
    $this->validate($props);
  }

  /**
   * A tally with a string count is rejected.
   */
  public function testTallyWithNonIntegerTotalIsRejected(): void {
    $props = $this->build()['#props'];
    $props['projects'] = [['name' => 'token', 'total' => 'three']];

    $this->expectException(InvalidComponentException::class);
    $this->expectExceptionMessage('[projects[0].total] String value found, but an integer is required');
    $this->validate($props);
  }

  /**
   * A missing required prop is rejected.
   */
  public function testMissingRequiredPropIsRejected(): void {
    $props = $this->build()['#props'];
    unset($props['totals']);

    $this->expectException(InvalidComponentException::class);
    $this->expectExceptionMessage('[totals] The property totals is required');
    $this->validate($props);
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
   * Validates props against the component schema.
   *
   * @param array<string, mixed> $props
   *   The props to validate.
   *
   * @throws \Drupal\Core\Render\Component\Exception\InvalidComponentException
   */
  private function validate(array $props): bool {
    $component = $this->component();
    return $this->container->get(ComponentValidator::class)->validateProps($props, $component);
  }

  private function component(): Component {
    return $this->container->get('plugin.manager.sdc')
      ->find('simplytest_tugboat:launch-statistics');
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
        'one_click_demos' => [['name' => 'Drupal CMS', 'total' => 5]],
        'projects' => [['name' => 'token', 'total' => 12]],
        'core_versions' => [['name' => '10.3.0', 'total' => 12]],
        'install_profiles' => [['name' => 'standard', 'total' => 12]],
        'project_types' => [['name' => 'Module', 'total' => 12]],
        'first_recorded_at' => 1756771200,
      ],
    ];
  }

}
