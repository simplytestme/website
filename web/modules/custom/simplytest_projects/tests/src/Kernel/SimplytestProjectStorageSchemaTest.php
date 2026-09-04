<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\simplytest_projects\SimplytestProjectStorageSchema
 * @group simplytest_projects
 */
final class SimplytestProjectStorageSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'simplytest_projects',
    'system',
    'user',
  ];

  /**
   * A fresh install indexes the columns projects are looked up by.
   *
   * @covers ::getEntitySchema
   */
  public function testInstallCreatesLookupIndexes(): void {
    $this->installEntitySchema('simplytest_project');

    $schema = $this->container->get('database')->schema();
    self::assertTrue($schema->indexExists('simplytest_project', 'simplytest_project__shortname'));
    self::assertTrue($schema->indexExists('simplytest_project', 'simplytest_project__timestamp'));
  }

  /**
   * The update hook adds the indexes to a table that predates them.
   *
   * Installs the entity type without the schema handler, the way every site
   * before update 9007 was installed, then runs the update.
   */
  public function testUpdateAddsIndexesToExistingTable(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    // Mutate the live definition rather than rebuilding it: a rebuild would
    // read the handler back out of the annotation.
    $entity_type_manager->getDefinition('simplytest_project')
      ->setHandlerClass('storage_schema', NULL);
    $this->installEntitySchema('simplytest_project');

    $schema = $this->container->get('database')->schema();
    self::assertFalse($schema->indexExists('simplytest_project', 'simplytest_project__shortname'));

    // Now behave like a deploy: the code declares the handler, the installed
    // definition does not, and the update hook reconciles them.
    $entity_type_manager->clearCachedDefinitions();
    $this->container->get('module_handler')->loadInclude('simplytest_projects', 'install');
    simplytest_projects_update_9007();

    self::assertTrue($schema->indexExists('simplytest_project', 'simplytest_project__shortname'));
    self::assertTrue($schema->indexExists('simplytest_project', 'simplytest_project__timestamp'));
  }

}
