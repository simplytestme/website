<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects\SimplytestProjectListBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Covers the module's procedural hooks and its entity list builder.
 *
 * @group simplytest
 * @group simplytest_project
 */
final class ProjectHooksTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'queue_unique',
    'simplytest_projects',
    'simplytest_projects_test',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installEntitySchema('user');
    $this->installEntitySchema('date_format');
    $this->installConfig(['system']);
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
  }

  /**
   * Cron refreshes core versions and queues stale projects for a refresh.
   */
  public function testCron(): void {
    $fresh = $this->createProject('token');
    $stale = $this->createProject('pathauto');
    $this->setTimestamp($stale, (int) strtotime('-5 hour'));

    $this->container->get('cron')->run();

    // Every supported core major was pulled in.
    $core_version_manager = $this->container->get('simplytest_projects.core_version_manager');
    self::assertNotEmpty($core_version_manager->getVersions(9));
    self::assertNotEmpty($core_version_manager->getVersions(10));

    // Only the stale project is queued.
    $queue = $this->container->get('queue')->get('simplytest_projects_project_refresher');
    self::assertEquals(1, $queue->numberOfItems());
    $item = $queue->claimItem();
    self::assertIsObject($item);
    self::assertEquals($stale->id(), $item->data);
    self::assertNotEquals($fresh->id(), $item->data);
  }

  /**
   * Inserting a project pulls its release history straight away.
   */
  public function testProjectInsertFetchesReleases(): void {
    $version_manager = $this->container->get('simplytest_projects.project_version_manager');
    self::assertEmpty($version_manager->getAllReleases('token'));

    $this->createProject('token');

    self::assertNotEmpty($version_manager->getAllReleases('token'));
  }

  /**
   * @covers \Drupal\simplytest_projects\SimplytestProjectListBuilder::buildHeader
   * @covers \Drupal\simplytest_projects\SimplytestProjectListBuilder::buildRow
   */
  public function testListBuilder(): void {
    $project = $this->createProject('token');

    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('simplytest_project');
    self::assertInstanceOf(SimplytestProjectListBuilder::class, $list_builder);

    $header = $list_builder->buildHeader();
    self::assertEquals(
      ['id', 'type', 'shortname', 'creator', 'timestamp', 'operations'],
      array_keys($header),
    );

    $row = $list_builder->buildRow($project);
    self::assertEquals(ProjectTypes::MODULE, $row['type']);
    self::assertEquals('token', $row['shortname']);
    self::assertEquals('Token', $row['id']->getText());
    self::assertNotEmpty($row['timestamp']);
    self::assertArrayHasKey('operations', $row);
  }

  /**
   * The subscriber shortens max-age on this module's own routes.
   *
   * @covers \Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber::onResponse
   */
  public function testMaxAgeIsShortenedOnProjectRoutes(): void {
    $response = new CacheableResponse();
    $this->dispatchResponse($response, 'simplytest_projects.core_versions');
    self::assertEquals(300, $response->getMaxAge());
  }

  /**
   * @dataProvider untouchedResponses
   *
   * @covers \Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber::onResponse
   */
  public function testMaxAgeIsUntouched(Response $response, string $route_name, int $request_type): void {
    $this->dispatchResponse($response, $route_name, $request_type);
    self::assertNotEquals(300, $response->getMaxAge());
  }

  public static function untouchedResponses(): \Generator {
    yield 'a route owned by another module' => [
      new CacheableResponse(),
      'system.admin',
      HttpKernelInterface::MAIN_REQUEST,
    ];
    yield 'a response that cannot carry cache metadata' => [
      new Response(),
      'simplytest_projects.core_versions',
      HttpKernelInterface::MAIN_REQUEST,
    ];
    yield 'a sub-request' => [
      new CacheableResponse(),
      'simplytest_projects.core_versions',
      HttpKernelInterface::SUB_REQUEST,
    ];
  }

  /**
   * Hands a response to the subscriber on its own.
   *
   * The subscriber is invoked directly rather than through the dispatcher:
   * core's FinishResponseSubscriber runs at a lower priority, which in Symfony
   * means later, and it rewrites Cache-Control before the response is sent.
   *
   * @todo the priority says "run after FinishResponseSubscriber" but a higher
   *   priority runs first, so the 300 second max-age never survives a real
   *   response. Decide whether the max-age is still wanted before changing it,
   *   since it would start being advertised to the CDN.
   */
  private function dispatchResponse(Response $response, string $route_name, int $request_type = HttpKernelInterface::MAIN_REQUEST): void {
    $request = Request::create('/whatever');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);

    $subscriber = new ModifyMaxAgeResponseSubscriber();
    $subscriber->onResponse(
      new ResponseEvent($this->container->get('http_kernel'), $request, $request_type, $response),
    );
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
    $this->container->get('database')->update('simplytest_project')
      ->fields(['timestamp' => $timestamp])
      ->condition('id', $project->id())
      ->execute();
  }

}
