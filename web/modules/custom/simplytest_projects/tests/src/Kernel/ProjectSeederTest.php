<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectSeeder;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects_test\TestDatabaseLockBackend;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ProjectSeeder
 */
final class ProjectSeederTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  private ProjectSeeder $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.seeder');
  }

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register('lock', TestDatabaseLockBackend::class)
      ->addArgument(new Reference('database'));
  }

  /**
   * @covers ::seed
   */
  public function testSeedsStarterProjects(): void {
    $seeded = $this->sut->seed();
    self::assertEquals(array_keys(ProjectSeeder::STARTER_PROJECTS), $seeded);

    $shortnames = $this->container->get('database')
      ->select('simplytest_project', 'p')
      ->fields('p', ['shortname'])
      ->execute()
      ->fetchCol();
    sort($shortnames);
    $expected = array_keys(ProjectSeeder::STARTER_PROJECTS);
    sort($expected);
    self::assertEquals($expected, $shortnames);

    // The static project info carried the title and type.
    $gin = $this->container->get('database')
      ->select('simplytest_project', 'p')
      ->fields('p', ['title', 'type'])
      ->condition('shortname', 'gin')
      ->execute()
      ->fetchAssoc();
    self::assertEquals(['title' => 'Gin Admin Theme', 'type' => 'Theme'], $gin);

    // Release data came along with the project.
    $releases = $this->container->get('simplytest_projects.project_version_manager')
      ->getAllReleases('token');
    self::assertNotEmpty($releases);
  }

  /**
   * @covers ::seed
   */
  public function testSkipsUnfetchableProjects(): void {
    self::assertEquals(['token'], $this->sut->seed(['notaproject', 'token']));
  }

  /**
   * Seeding twice must not fail or duplicate projects.
   *
   * @covers ::seed
   */
  public function testSeedIsIdempotent(): void {
    $this->sut->seed(['token']);
    self::assertEquals(['token'], $this->sut->seed(['token']));

    $count = (int) $this->container->get('database')
      ->select('simplytest_project', 'p')
      ->countQuery()
      ->execute()
      ->fetchField();
    self::assertEquals(1, $count);
  }

}
