<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytesyt_projects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;

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

  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
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
  }

}
