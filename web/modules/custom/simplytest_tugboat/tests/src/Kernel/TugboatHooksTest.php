<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_tugboat\LaunchRecorder;

/**
 * Covers the module's procedural hooks.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class TugboatHooksTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_ocd',
    'simplytest_projects',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_handler')->loadInclude('simplytest_tugboat', 'install');
  }

  /**
   * @covers ::simplytest_tugboat_schema
   */
  public function testSchemaDefinition(): void {
    $schema = simplytest_tugboat_schema();

    self::assertArrayHasKey(LaunchRecorder::TABLE_NAME, $schema);
    $table = $schema[LaunchRecorder::TABLE_NAME];

    self::assertEquals(['id'], $table['primary key']);
    // Every report filters on status before narrowing or grouping, so each
    // index has to lead with it.
    foreach ($table['indexes'] as $name => $columns) {
      self::assertEquals('status', $columns[0], "Index $name does not lead with status.");
    }

    // The launch record deliberately holds nothing that identifies a person.
    $columns = array_keys($table['fields']);
    self::assertNotContains('hostname', $columns);
    self::assertNotContains('uid', $columns);
    self::assertNotContains('patches', $columns);
    self::assertContains('patch_count', $columns);
  }

  /**
   * @covers ::simplytest_tugboat_theme
   */
  public function testThemeDefinition(): void {
    $theme = simplytest_tugboat_theme();

    self::assertArrayHasKey('simplytest_launch_statistics', $theme);
    self::assertArrayHasKey('totals', $theme['simplytest_launch_statistics']['variables']);
    self::assertArrayHasKey('projects', $theme['simplytest_launch_statistics']['variables']);
  }

}
