<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_ocd\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_ocd\Controller\Resources;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

#[CoversClass(Resources::class)]
#[CoversMethod(Resources::class, 'info')]
#[CoversMethod(Resources::class, 'launch')]
#[CoversMethod(Resources::class, 'demo')]
#[CoversMethod(Resources::class, 'demoTitle')]
#[CoversMethod(OneClickDemoPluginManager::class, '__construct')]
#[Group('simplytest')]
#[Group('simplytest_ocd')]
#[RunTestsInSeparateProcesses]
final class ResourcesTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);

    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();
  }

  public function testInfoListsEveryDemo(): void {
    $url = Url::fromRoute('simplytest_ocd.ocd');
    $request = Request::create($url->toString(), 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $this->container->get('http_kernel')->handle($request);
    self::assertEquals(200, $response->getStatusCode());

    $data = Json::decode((string) $response->getContent());
    $ids = array_column($data, 'id');
    self::assertContains('oneclickdemo_umami', $ids);
    self::assertContains('oneclickdemo_commerce', $ids);
    self::assertContains('starshot', $ids);

    // Only the keys the front end needs are exposed.
    foreach ($data as $definition) {
      self::assertEquals(
        ['id', 'title', 'base_preview_name', 'description', 'weight', 'recommended', 'screenshot'],
        array_keys($definition)
      );
    }

    // Demos are ordered by weight so the tile grid is stable: the recommended
    // demo first.
    self::assertEquals(['starshot', 'oneclickdemo_commerce', 'oneclickdemo_umami', 'oneclickdemo_agent_access'], $ids);
    self::assertTrue($data[0]['recommended']);
  }

  /**
   * The response is invalidated when the plugin definitions change.
   */
  public function testInfoIsCacheable(): void {
    $response = Resources::create($this->container)->info();

    self::assertInstanceOf(CacheableJsonResponse::class, $response);
    self::assertContains('oneclickdemo', $response->getCacheableMetadata()->getCacheTags());
  }

  public function testLaunch(): void {
    $response = Resources::create($this->container)->launch('oneclickdemo_umami');

    $data = Json::decode((string) $response->getContent());
    self::assertEquals('OK', $data['status']);
    self::assertEquals('clone123', $data['tugboat']['preview_id']);
    self::assertStringContainsString('/progress/clone123/cj123', $data['progress']);

    // The demo is a clone of its own base preview.
    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews/base-umami-id/clone');
    self::assertEquals('simplytest', $payload['name']);
  }

  public function testLaunchRejectsUnknownDemo(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('nope is not a valid option');
    Resources::create($this->container)->launch('nope');
  }

  public function testLaunchWhenTugboatIsUnreachable(): void {
    $this->config('tugboat.settings')->set('repository_id', 'brokenrepo')->save();

    $this->expectException(ServiceUnavailableHttpException::class);
    Resources::create($this->container)->launch('oneclickdemo_umami');
  }

  /**
   * Each demo has a landing page that shows it without launching it.
   */
  public function testADemoHasALandingPage(): void {
    self::assertSame(
      '/demo/agent-access',
      Url::fromRoute('simplytest_ocd.demo', ['slug' => 'agent-access'])->toString(),
    );

    $resources = Resources::create($this->container);
    self::assertSame('Agent Access', $resources->demoTitle('agent-access'));

    $build = $resources->demo('agent-access');
    self::assertSame(
      [
        'id' => 'oneclickdemo_agent_access',
        'title' => 'Agent Access',
        'description' => "Drupal CMS, ready for AI agents. Connect one to the site's /mcp URL and sign in as admin.",
        'screenshot' => NULL,
      ],
      $build['mount']['#attached']['drupalSettings']['demo'],
    );
    self::assertContains('oneclickdemo', $build['#cache']['tags']);

    // Viewing the page did not contact Tugboat, so nothing was launched.
    self::assertNull($this->container->get('state')->get('tugboat.preview_list_requests'));
  }

  /**
   * A demo's screenshot is served from the module that provides it.
   */
  public function testScreenshotsResolveToFilesInTheModule(): void {
    $data = Json::decode((string) Resources::create($this->container)->info()->getContent());
    $screenshots = array_column($data, 'screenshot', 'id');

    $module_path = $this->container->get('extension.list.module')->getPath('simplytest_ocd');
    self::assertSame(
      base_path() . $module_path . '/images/umami.webp',
      $screenshots['oneclickdemo_umami'],
    );
    foreach (['starshot', 'oneclickdemo_commerce', 'oneclickdemo_umami'] as $id) {
      $path = substr((string) $screenshots[$id], strlen(base_path()));
      self::assertFileExists($this->root . '/' . $path, $id);
    }
    // Agent Access looks like Drupal CMS, so its tile draws its own preview.
    self::assertNull($screenshots['oneclickdemo_agent_access']);
  }

  /**
   * Every demo tile has a landing page.
   */
  public function testEveryDemoHasASlug(): void {
    $resources = Resources::create($this->container);
    $titles = array_map(
      $resources->demoTitle(...),
      ['drupal-cms', 'commerce-kickstart', 'umami', 'agent-access'],
    );
    self::assertSame(
      array_column(Json::decode((string) $resources->info()->getContent()), 'title'),
      $titles,
    );
  }

  /**
   * A landing page for a slug no demo has is a 404.
   */
  public function testAnUnknownDemoLandingPageIsNotFound(): void {
    try {
      Resources::create($this->container)->demo('nope');
      self::fail('An unknown demo should not render.');
    }
    catch (CacheableNotFoundHttpException $e) {
      self::assertSame('nope is not a demo', $e->getMessage());
      self::assertContains('oneclickdemo', $e->getCacheTags());
    }

    // The plugin ID is not the slug.
    $this->expectException(CacheableNotFoundHttpException::class);
    Resources::create($this->container)->demo('oneclickdemo_agent_access');
  }

  public function testPluginManagerDefinitions(): void {
    $manager = $this->container->get('plugin.manager.oneclickdemo');

    $definition = $manager->getDefinition('oneclickdemo_umami');
    self::assertEquals('umami', $definition['base_preview_name']);
    self::assertEquals('Umami', (string) $definition['title']);

    self::assertTrue($manager->hasDefinition('oneclickdemo_commerce'));
    self::assertFalse($manager->hasDefinition('nope'));
  }

}
