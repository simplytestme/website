<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectFetcher;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
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

  private ProjectFetcher $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.fetcher');
  }

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container
      ->register('lock', TestDatabaseLockBackend::class)
      ->addArgument(new Reference('database'));
  }

  public function testFetchProject(): void {
    $result = $this->sut->fetchProject('token');
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
    $result = $this->sut->fetchProject('token');
    self::assertNotNull($result);
  }

  /**
   * @covers ::fetchProject
   */
  public function testFetchProjectIsCaseInsensitive(): void {
    $result = $this->sut->fetchProject('ToKeN');
    self::assertNotNull($result);
    self::assertEquals('token', $result['shortname']);
  }

  /**
   * A second process holding the lock must not double up the work.
   *
   * @covers ::fetchProject
   */
  public function testFetchProjectReturnsNullWhenLockIsHeld(): void {
    $lock = $this->container->get('lock');
    self::assertInstanceOf(TestDatabaseLockBackend::class, $lock);
    self::assertTrue($lock->acquire('fetch_project_token'));
    // Take on a new lock ID so the fetcher looks like a second process rather
    // than the one already holding the lock.
    $lock->resetLockId();

    self::assertNull($this->sut->fetchProject('token'));
  }

  /**
   * The response body is cached, so a repeat fetch makes no second request.
   *
   * @covers ::fetchProject
   */
  public function testFetchProjectUsesCache(): void {
    $this->sut->fetchProject('token');
    self::assertNotFalse($this->container->get('cache.data')->get('project_fetch:token'));

    $lock = $this->container->get('lock');
    self::assertInstanceOf(TestDatabaseLockBackend::class, $lock);
    $lock->resetLockId();

    // The mocked middleware would answer again, so assert on the entity count:
    // a cached body still produces the same result without a duplicate project.
    $result = $this->sut->fetchProject('token');
    self::assertEquals('Token', $result['title']);
    self::assertCount(1, $this->projectIds('token'));
  }

  /**
   * @covers ::fetchProject
   */
  public function testFetchSandboxProject(): void {
    $result = $this->sut->fetchProject('sandboxed');
    self::assertNotNull($result);
    self::assertTrue($result['sandbox']);
    self::assertEquals('someuser', $result['creator']);
  }

  /**
   * @dataProvider unfetchableProjects
   *
   * @covers ::fetchProject
   */
  public function testFetchProjectFailure(string $shortname): void {
    self::assertNull($this->sut->fetchProject($shortname));
    self::assertCount(0, $this->projectIds($shortname));

    // Whatever went wrong, the lock has to be released for the next attempt.
    $lock = $this->container->get('lock');
    self::assertInstanceOf(TestDatabaseLockBackend::class, $lock);
    $lock->resetLockId();
    self::assertTrue($lock->lockMayBeAvailable("fetch_project_$shortname"));
  }

  public static function unfetchableProjects(): \Generator {
    yield 'project is unknown to Drupal.org' => ['notaproject'];
    yield 'response is not JSON' => ['malformed'];
    yield 'node has no title' => ['no_title'];
    yield 'node has no type' => ['no_type'];
    yield 'node type is not a project type' => ['weird_type'];
    yield 'sandbox node has no URL' => ['sandbox_no_url'];
    yield 'Drupal.org responds with a server error' => ['servererror'];
    yield 'Drupal.org responds with a 404' => ['notfound'];
  }

  /**
   * Characters that are invalid in a lock key do not stop the fetch.
   *
   * @covers ::fetchProject
   */
  public function testFetchProjectSanitizesLockKey(): void {
    $result = $this->sut->fetchProject('not.a-project');
    self::assertNotNull($result);
    self::assertEquals('not.a-project', $result['shortname']);
    // The sanitized key is what the lock was taken and released under.
    self::assertTrue($this->container->get('lock')->lockMayBeAvailable('fetch_project_not_a_project'));
  }

  /**
   * @covers ::fetchVersions
   */
  public function testFetchVersionsWithoutForce(): void {
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');
    $versions = $this->sut->fetchVersions('token');
    self::assertNotEmpty($versions);
    self::assertContains('8.x-1.9', array_map(static fn(\stdClass $row) => $row->version, $versions));
  }

  /**
   * @covers ::fetchVersions
   */
  public function testForcedFetchVersionsForUnknownProject(): void {
    self::assertFalse($this->sut->fetchVersions('notaproject', TRUE));
  }

  /**
   * A project refreshed within the last six hours is left alone.
   *
   * @covers ::fetchVersions
   */
  public function testForcedFetchVersionsSkipsFreshProject(): void {
    $project = $this->createProject('token');
    $original_timestamp = $project->getTimestamp();

    $versions = $this->sut->fetchVersions('token', TRUE);
    self::assertNotEmpty($versions);

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    $storage->resetCache();
    self::assertEquals($original_timestamp, $storage->load($project->id())->getTimestamp());
  }

  /**
   * A stale project is refreshed and stamped with the current request time.
   *
   * @covers ::fetchVersions
   */
  public function testForcedFetchVersionsRefreshesStaleProject(): void {
    $project = $this->createProject('pathauto');
    $stale = (int) $this->container->get('datetime.time')->getRequestTime() - (60 * 60 * 24);
    // `timestamp` is a changed field, so write around the entity API to keep it
    // from being stamped with the current time on save.
    $this->container->get('database')->update('simplytest_project')
      ->fields(['timestamp' => $stale])
      ->condition('id', $project->id())
      ->execute();

    $versions = $this->sut->fetchVersions('pathauto', TRUE);
    self::assertNotEmpty($versions);

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    $storage->resetCache();
    self::assertGreaterThan($stale, $storage->load($project->id())->getTimestamp());
  }

  /**
   * @covers ::searchFromProjects
   */
  public function testSearchFromProjects(): void {
    $this->createProject('token', 'Token', ProjectTypes::MODULE, usage: 500);
    $this->createProject('pathauto', 'Pathauto', ProjectTypes::MODULE, usage: 900);
    $this->createProject('bootstrap', 'Bootstrap', ProjectTypes::THEME, usage: 100);

    // Ordered by usage, descending.
    $results = $this->sut->searchFromProjects('o');
    self::assertEquals(
      ['pathauto', 'token', 'bootstrap'],
      array_column($results, 'shortname'),
    );
    // `sandbox` is stripped from the results.
    self::assertArrayNotHasKey('sandbox', $results[0]);

    // Matching happens on the title as well as the shortname.
    self::assertCount(1, $this->sut->searchFromProjects('Pathauto'));
    self::assertCount(0, $this->sut->searchFromProjects('nothing matches this'));
  }

  /**
   * @covers ::searchFromProjects
   */
  public function testSearchFromProjectsFiltersByType(): void {
    $this->createProject('token', 'Token', ProjectTypes::MODULE);
    $this->createProject('bootstrap', 'Bootstrap', ProjectTypes::THEME);

    $results = $this->sut->searchFromProjects('o', 100, [ProjectTypes::THEME]);
    self::assertEquals(['bootstrap'], array_column($results, 'shortname'));

    $results = $this->sut->searchFromProjects('o', 100, [ProjectTypes::THEME, ProjectTypes::MODULE]);
    self::assertCount(2, $results);
  }

  /**
   * @covers ::searchFromProjects
   */
  public function testSearchFromProjectsRespectsRange(): void {
    $this->createProject('token', 'Token', ProjectTypes::MODULE, usage: 500);
    $this->createProject('pathauto', 'Pathauto', ProjectTypes::MODULE, usage: 900);

    self::assertCount(1, $this->sut->searchFromProjects('o', 1));
  }

  /**
   * @covers ::searchFromProjects
   */
  public function testSearchEscapesLikeWildcards(): void {
    $this->createProject('token', 'Token', ProjectTypes::MODULE);

    // Without escaping, `%` would match every project.
    self::assertCount(0, $this->sut->searchFromProjects('%'));
  }

  /**
   * A duplicate import reports success and leaves the original untouched.
   *
   * Two lookups for the same project can race; whichever loses the save must
   * still tell its caller the project exists, and must not disturb the row
   * the winner created.
   *
   * @covers ::fetchProject
   */
  public function testFetchProjectDuplicateKeepsOriginal(): void {
    self::assertNotNull($this->sut->fetchProject('token'));
    $original_ids = $this->projectIds('token');
    self::assertCount(1, $original_ids);

    self::assertNotNull($this->sut->fetchProject('token'));

    // Still exactly the one entity, with its original ID.
    self::assertEquals($original_ids, $this->projectIds('token'));
    $logger = $this->container->get('simplytest_projects_test.logger');
    self::assertStringContainsString(
      'Skipped saving token: it was imported concurrently.',
      implode("\n", $logger->getMessages()),
    );
  }

  /**
   * @return array<int|string, string>
   */
  private function projectIds(string $shortname): array {
    return $this->container->get('entity_type.manager')
      ->getStorage('simplytest_project')
      ->getQuery('AND')
      ->accessCheck(FALSE)
      ->condition('shortname', $shortname)
      ->execute();
  }

  private function createProject(string $shortname, ?string $title = NULL, string $type = ProjectTypes::MODULE, int $usage = 0): SimplytestProject {
    $project = SimplytestProject::create([
      'title' => $title ?? ucfirst($shortname),
      'shortname' => $shortname,
      'sandbox' => "0",
      'type' => $type,
      'usage' => $usage,
    ]);
    $project->save();
    return $project;
  }

}
