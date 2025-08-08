<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\simplytest_projects_test\TestDatabaseLockBackend;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ProjectFetcher
 */
final class ProjectFetcherTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
  }

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register('lock', TestDatabaseLockBackend::class)
      ->addArgument(new Reference('database'));
  }

  public function testFetchProject(): void {
    $sut = $this->container->get('simplytest_projects.fetcher');
    $result = $sut->fetchProject('token');
    self::assertNotNull($result);
    self::assertEquals([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => FALSE,
      'type' => 'Module',
      'creator' => NULL,
      'usage' => 695647,
    ], $result);

    $lock = $this->container->get('lock');
    self::assertInstanceOf(TestDatabaseLockBackend::class, $lock);
    $lock->resetLockId();

    // Verify lock is released.
    $result = $sut->fetchProject('token');
    self::assertNotNull($result);
  }

}
