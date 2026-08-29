<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects_test\BufferedLogger;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\Plugin\QueueWorker\ProjectRefresher
 */
final class ProjectRefresherTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  private QueueWorkerInterface $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('plugin.manager.queue_worker')
      ->createInstance('simplytest_projects_project_refresher');
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItem(): void {
    $project = $this->createProject('token');
    $this->setTimestamp($project, 0);

    $this->sut->processItem($project->id());

    $project = $this->reload($project);
    self::assertEquals(695647, (int) $project->get('usage')->value);
    self::assertGreaterThan(0, $project->getTimestamp());

    // The release history for the project was pulled in as well.
    $releases = $this->container->get('simplytest_projects.project_version_manager')
      ->getAllReleases('token');
    self::assertNotEmpty($releases);
  }

  /**
   * A queue item pointing at a deleted project is logged and dropped.
   *
   * @covers ::processItem
   */
  public function testProcessItemForMissingProject(): void {
    $this->sut->processItem(9999);

    self::assertStringContainsString(
      'Could not load project ID `9999` for project refresh.',
      $this->logMessages(),
    );
  }

  /**
   * A 5xx from Drupal.org suspends the queue rather than burning items.
   *
   * @covers ::processItem
   */
  public function testProcessItemSuspendsQueueWhenDrupalOrgIsDown(): void {
    $project = $this->createProject('servererror');

    $this->expectException(SuspendQueueException::class);
    $this->expectExceptionMessage('Drupal.org API may be down.');
    $this->sut->processItem($project->id());
  }

  /**
   * Any other API failure still lets the release history refresh proceed.
   *
   * @covers ::processItem
   */
  public function testProcessItemToleratesOtherApiFailures(): void {
    $project = $this->createProject('notfound');
    $this->setTimestamp($project, 0);

    $this->sut->processItem($project->id());

    self::assertGreaterThan(0, $this->reload($project)->getTimestamp());
  }

  /**
   * A project that no longer validates is logged instead of throwing.
   *
   * @covers ::processItem
   */
  public function testProcessItemLogsValidationErrors(): void {
    $project = $this->createProject('token');
    // A duplicate shortname trips the UniqueField constraint on save.
    $this->container->get('database')->insert('simplytest_project')
      ->fields([
        'title' => 'Token clone',
        'shortname' => 'token',
        'sandbox' => 0,
        'type' => ProjectTypes::MODULE,
        'timestamp' => 0,
        'usage' => 0,
      ])
      ->execute();

    $this->sut->processItem($project->id());

    self::assertStringContainsString('Validation errors when saving project', $this->logMessages());
  }

  private function createProject(string $shortname): SimplytestProject {
    $project = SimplytestProject::create([
      'title' => ucfirst($shortname),
      'shortname' => $shortname,
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ]);
    $project->save();
    return $project;
  }

  private function setTimestamp(SimplytestProject $project, int $timestamp): void {
    // `timestamp` is a changed field, so it cannot be set through the entity.
    $this->container->get('database')->update('simplytest_project')
      ->fields(['timestamp' => $timestamp])
      ->condition('id', $project->id())
      ->execute();
  }

  private function reload(SimplytestProject $project): SimplytestProject {
    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    $storage->resetCache();
    return $storage->load($project->id());
  }

  private function logMessages(): string {
    $logger = $this->container->get('simplytest_projects_test.logger');
    return implode("\n", $logger->getMessages());
  }

}
