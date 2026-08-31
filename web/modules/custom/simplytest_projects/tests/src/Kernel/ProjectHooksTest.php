<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\EventSubscriber\FinishResponseSubscriber;
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
use Symfony\Component\HttpKernel\KernelEvents;

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
   * The subscriber runs after everything else that writes Cache-Control.
   *
   * Core's FinishResponseSubscriber rebuilds the header from scratch at
   * priority 0, and http_cache_control adds s-maxage at -10. A subscriber that
   * runs before either of them has its work thrown away.
   *
   * @covers \Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber::getSubscribedEvents
   */
  public function testSubscriberRunsAfterCoreCacheControl(): void {
    $call_order = [];
    foreach ($this->container->get('event_dispatcher')->getListeners(KernelEvents::RESPONSE) as $listener) {
      $call_order[] = $listener[0]::class . '::' . $listener[1];
    }

    $ours = array_search(ModifyMaxAgeResponseSubscriber::class . '::onResponse', $call_order, TRUE);
    $core = array_search(FinishResponseSubscriber::class . '::onRespond', $call_order, TRUE);
    self::assertIsInt($ours);
    self::assertIsInt($core);
    self::assertGreaterThan($core, $ours);
  }

  /**
   * @covers \Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber::onResponse
   */
  public function testMaxAgeIsShortenedOnProjectRoutes(): void {
    $response = self::publicResponse();
    $this->dispatchResponse($response, 'simplytest_projects.core_versions');

    self::assertEquals(300, self::maxAge($response));
    // Shortening the browser lifetime must not make the response private, or
    // the CDN stops storing it altogether.
    self::assertTrue($response->headers->hasCacheControlDirective('public'));
    // The CDN keeps the lifetime http_cache_control configured for it.
    self::assertEquals('600', $response->headers->getCacheControlDirective('s-maxage'));
  }

  /**
   * The Surrogate-Control max-age is rewritten to the CDN lifetime.
   *
   * The fastly module copies Cache-Control into Surrogate-Control at priority
   * 0, before this subscriber runs, and Fastly prefers that header over
   * s-maxage. Left alone, the edge would keep these routes for the site-wide
   * 32 days.
   *
   * @covers \Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber::onResponse
   */
  public function testSurrogateControlIsCappedForTheCdn(): void {
    $response = self::publicResponse();
    // What fastly's AddStaleHeaders builds from the site's configuration.
    $response->headers->set('Surrogate-Control', 'public, max-age=2764800, stale-while-revalidate=14440, stale-if-error=604800');
    $this->dispatchResponse($response, 'simplytest_projects.core_versions');

    self::assertEquals(
      'public, max-age=600, stale-while-revalidate=14440, stale-if-error=604800',
      $response->headers->get('Surrogate-Control'),
    );
  }

  /**
   * @dataProvider untouchedResponses
   *
   * @covers \Drupal\simplytest_projects\EventSubscriber\ModifyMaxAgeResponseSubscriber::onResponse
   */
  public function testMaxAgeIsUntouched(Response $response, string $route_name, int $request_type): void {
    $before = self::maxAge($response);
    $this->dispatchResponse($response, $route_name, $request_type);
    self::assertEquals($before, self::maxAge($response));
  }

  public static function untouchedResponses(): \Generator {
    yield 'a route owned by another module' => [
      self::publicResponse(),
      'system.admin',
      HttpKernelInterface::MAIN_REQUEST,
    ];
    yield 'a response that cannot carry cache metadata' => [
      new Response(),
      'simplytest_projects.core_versions',
      HttpKernelInterface::MAIN_REQUEST,
    ];
    yield 'a sub-request' => [
      self::publicResponse(),
      'simplytest_projects.core_versions',
      HttpKernelInterface::SUB_REQUEST,
    ];
    // Core withholds `public` when its cache policies deny the request, which
    // is how an authenticated response arrives here.
    yield 'a response core did not mark public' => [
      (new CacheableResponse())->setPrivate()->setMaxAge(2764800),
      'simplytest_projects.core_versions',
      HttpKernelInterface::MAIN_REQUEST,
    ];
    // Nothing sets a shorter max-age today, but raising one back up to 300
    // would be a regression rather than a fix.
    yield 'a max-age already below the ceiling' => [
      self::publicResponse()->setMaxAge(60),
      'simplytest_projects.core_versions',
      HttpKernelInterface::MAIN_REQUEST,
    ];
  }

  /**
   * A response in the state core's FinishResponseSubscriber leaves behind.
   *
   * The values are the site's own configuration: a 32 day max-age from
   * system.performance, and a 600 second s-maxage from http_cache_control.
   */
  private static function maxAge(Response $response): ?string {
    $directive = $response->headers->getCacheControlDirective('max-age');
    return is_string($directive) ? $directive : NULL;
  }

  private static function publicResponse(): CacheableResponse {
    $response = new CacheableResponse();
    $response->setPublic();
    $response->setMaxAge(2764800);
    $response->setSharedMaxAge(600);
    return $response;
  }

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
