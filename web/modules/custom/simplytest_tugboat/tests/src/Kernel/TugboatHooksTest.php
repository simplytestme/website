<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_tugboat\LaunchRecorder;

/**
 * Covers the module's schema and update hooks.
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
   * The update hook creates the table on a site that is already installed.
   *
   * hook_schema() only runs when a module is first installed, so without this
   * the production database never gets the table and every launch is logged as
   * a recording failure.
   *
   * @covers ::simplytest_tugboat_update_10001
   */
  public function testUpdateInstallsTheTable(): void {
    $schema = $this->container->get('database')->schema();
    self::assertFalse($schema->tableExists(LaunchRecorder::TABLE_NAME));

    simplytest_tugboat_update_10001();

    self::assertTrue($schema->tableExists(LaunchRecorder::TABLE_NAME));
  }

  /**
   * Running the update twice is harmless.
   *
   * @covers ::simplytest_tugboat_update_10001
   */
  public function testUpdateIsIdempotent(): void {
    simplytest_tugboat_update_10001();
    simplytest_tugboat_update_10001();

    self::assertTrue(
      $this->container->get('database')->schema()->tableExists(LaunchRecorder::TABLE_NAME)
    );
  }

}
