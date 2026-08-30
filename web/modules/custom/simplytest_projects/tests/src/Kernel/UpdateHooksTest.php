<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * Covers the module's update hooks.
 *
 * The schemas are deliberately not installed in setUp(): the update hooks are
 * what put them there, which is the behavior under test.
 *
 * @group simplytest
 * @group simplytest_project
 */
final class UpdateHooksTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->container->get('module_handler')->loadInclude('simplytest_projects', 'install');
  }

  /**
   * @covers ::simplytest_projects_schema
   */
  public function testSchemaDefinition(): void {
    $schema = simplytest_projects_schema();

    self::assertArrayHasKey(CoreVersionManager::TABLE_NAME, $schema);
    self::assertArrayHasKey(ProjectVersionManager::TABLE_NAME, $schema);
    self::assertEquals(['version'], $schema[CoreVersionManager::TABLE_NAME]['primary key']);
    self::assertEquals(
      ['short_name', 'version'],
      $schema[ProjectVersionManager::TABLE_NAME]['primary key'],
    );
  }

  /**
   * @covers ::simplytest_projects_update_9001
   */
  public function testUpdate9001CreatesCoreVersionsTable(): void {
    $db_schema = $this->container->get('database')->schema();
    self::assertFalse($db_schema->tableExists(CoreVersionManager::TABLE_NAME));

    simplytest_projects_update_9001();

    self::assertTrue($db_schema->tableExists(CoreVersionManager::TABLE_NAME));
  }

  /**
   * @covers ::simplytest_projects_update_9002
   */
  public function testUpdate9002CreatesProjectVersionsTable(): void {
    $db_schema = $this->container->get('database')->schema();
    self::assertFalse($db_schema->tableExists(ProjectVersionManager::TABLE_NAME));

    simplytest_projects_update_9002();

    self::assertTrue($db_schema->tableExists(ProjectVersionManager::TABLE_NAME));
  }

  /**
   * @covers ::simplytest_projects_update_9004
   */
  public function testUpdate9004RefreshesCoreReleaseData(): void {
    simplytest_projects_update_9001();
    simplytest_projects_update_9002();

    $state = $this->container->get('state');
    $state->set('release_history_last_modified:drupal:10.x', 12345);

    simplytest_projects_update_9004();

    self::assertNull($state->get('release_history_last_modified:drupal:10.x'));
    self::assertNotEmpty($this->container->get('simplytest_projects.core_version_manager')->getVersions(10));
  }

  /**
   * @covers ::simplytest_projects_update_9005
   */
  public function testUpdate9005ChangesVersionFieldsToIntegers(): void {
    simplytest_projects_update_9001();

    simplytest_projects_update_9005();

    // The table survives the change and still accepts a row.
    $this->container->get('database')->insert(CoreVersionManager::TABLE_NAME)
      ->fields([
        'version' => '10.1.0',
        'major' => 10,
        'minor' => 1,
        'patch' => 0,
        'extra' => NULL,
        'vcs_label' => '10.1.0',
        'insecure' => 0,
      ])
      ->execute();

    $versions = $this->container->get('simplytest_projects.core_version_manager')->getVersions(10);
    self::assertCount(1, $versions);
  }

  /**
   * @covers ::simplytest_projects_update_9006
   */
  public function testUpdate9006RemovesLegacyQueueItems(): void {
    $database = $this->container->get('database');
    $database->schema()->createTable('queue', [
      'fields' => [
        'item_id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
        'name' => ['type' => 'varchar_ascii', 'length' => 255, 'not null' => TRUE, 'default' => ''],
        'data' => ['type' => 'blob', 'not null' => FALSE, 'size' => 'big'],
        'expire' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'created' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['item_id'],
    ]);

    foreach (['simplytest_projects_project_refresher', 'something_else'] as $name) {
      $database->insert('queue')
        ->fields(['name' => $name, 'data' => serialize(1), 'expire' => 0, 'created' => 0])
        ->execute();
    }

    simplytest_projects_update_9006();

    $remaining = $database->select('queue', 'q')->fields('q', ['name'])->execute()->fetchCol();
    self::assertEquals(['something_else'], $remaining);
  }

}
