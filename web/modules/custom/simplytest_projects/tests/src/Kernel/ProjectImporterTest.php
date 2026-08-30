<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectImporter;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ProjectImporter
 */
final class ProjectImporterTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  private ProjectImporter $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.importer');
  }

  /**
   * @covers ::fetchData
   */
  public function testFetchData(): void {
    $items = $this->sut->fetchData('project_module');
    self::assertIsArray($items);
    self::assertCount(3, $items['list']);
    self::assertEquals('module_0_1', $items['list'][0]['field_project_machine_name']);
  }

  /**
   * @covers ::fetchData
   */
  public function testFetchDataAcceptsAPage(): void {
    $items = $this->sut->fetchData('project_theme', 2);
    self::assertEquals('theme_2_1', $items['list'][0]['field_project_machine_name']);
  }

  /**
   * @covers ::fetchData
   */
  public function testFetchDataRejectsUnknownType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The type 'project_nope' is not allowed");
    $this->sut->fetchData('project_nope');
  }

  /**
   * @covers ::filterExistingProjects
   */
  public function testFilterExistingProjects(): void {
    SimplytestProject::create([
      'title' => 'Module 0 1',
      'shortname' => 'module_0_1',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ])->save();

    $items = $this->sut->fetchData('project_module');
    $data = $this->sut->filterExistingProjects($items['list']);

    // The project already stored is filtered out.
    self::assertEquals(['module_0_2', 'module_0_3'], array_column($data, 'shortname'));
    self::assertEquals([
      'title' => 'Module 0 2',
      'shortname' => 'module_0_2',
      'sandbox' => 0,
      'type' => ProjectTypes::MODULE,
      'creator' => 'someuser',
    ], $data[0]);
  }

  /**
   * @covers ::filterExistingProjects
   */
  public function testFilterExistingProjectsHandlesSandboxAndMissingAuthor(): void {
    $data = $this->sut->filterExistingProjects([
      [
        'title' => 'A sandbox',
        'field_project_machine_name' => 'a_sandbox',
        'field_project_type' => 'sandbox',
        'type' => 'project_module',
        'author' => [],
      ],
    ]);

    self::assertEquals(1, $data[0]['sandbox']);
    self::assertEquals('', $data[0]['creator']);
  }

  /**
   * @covers ::buildBatch
   */
  public function testBuildBatch(): void {
    $batch = $this->sut->buildBatch('module');

    $array = $batch->toArray();
    // The fixture reports `last` as page 2, so pages 0 through 2 get queued.
    self::assertCount(3, $array['operations']);
    self::assertEquals([ProjectImporter::class, 'batchProcess'], $array['operations'][0][0]);
    self::assertEquals([0, 'project_module'], $array['operations'][0][1]);
    self::assertEquals('Importing 3 pages of module', (string) $array['title']);
  }

  /**
   * @covers ::buildBatch
   */
  public function testBuildBatchRejectsUnknownType(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("The type 'widget' is not allowed.");
    $this->sut->buildBatch('widget');
  }

  /**
   * @covers ::batchProcess
   */
  public function testBatchProcess(): void {
    // The Batch API hands operations an empty context; batchProcess has to
    // initialize its own counter.
    $context = [];
    ProjectImporter::batchProcess(0, 'project_module', $context);

    self::assertEquals(3, $context['results']['processed']);
    self::assertEquals('project_module', $context['results']['type']);

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    self::assertCount(3, $storage->loadMultiple());
  }

  /**
   * A project that is already stored does not stop the rest of the batch.
   *
   * @covers ::batchProcess
   */
  public function testBatchProcessToleratesDuplicates(): void {
    $context = [];
    ProjectImporter::batchProcess(0, 'project_module', $context);
    ProjectImporter::batchProcess(0, 'project_module', $context);

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    self::assertCount(3, $storage->loadMultiple());
  }

  /**
   * @covers ::batchFinished
   */
  public function testBatchFinishedOnSuccess(): void {
    ProjectImporter::batchFinished(TRUE, ['processed' => 6, 'type' => 'project_module'], []);

    $messages = $this->container->get('messenger')->messagesByType('status');
    self::assertEquals('Total 6 module imported.', (string) $messages[0]);
  }

  /**
   * @covers ::batchFinished
   */
  public function testBatchFinishedOnFailure(): void {
    ProjectImporter::batchFinished(FALSE, [], [['do_the_thing', ['an argument']]]);

    $messages = $this->container->get('messenger')->messagesByType('status');
    self::assertStringContainsString('An error occurred while processing do_the_thing', (string) $messages[0]);
  }

}
