<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_ocd\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_ocd\Controller\Resources;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * @group simplytest
 * @group simplytest_ocd
 *
 * @coversDefaultClass \Drupal\simplytest_ocd\Controller\Resources
 */
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

  /**
   * @covers ::info
   */
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
        ['id', 'title', 'base_preview_name', 'description', 'meta', 'weight', 'recommended'],
        array_keys($definition)
      );
    }

    // Demos are ordered by weight so the tile grid is stable: the recommended
    // demo first.
    self::assertEquals(['starshot', 'oneclickdemo_commerce', 'oneclickdemo_umami'], $ids);
    self::assertTrue($data[0]['recommended']);
    self::assertEquals('drupal_cms · 1.x', $data[0]['meta']);
  }

  /**
   * The response is invalidated when the plugin definitions change.
   *
   * @covers ::info
   */
  public function testInfoIsCacheable(): void {
    $response = Resources::create($this->container)->info();

    self::assertInstanceOf(CacheableJsonResponse::class, $response);
    self::assertContains('oneclickdemo', $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * @covers ::launch
   */
  public function testLaunch(): void {
    $response = Resources::create($this->container)->launch('oneclickdemo_umami');

    $data = Json::decode((string) $response->getContent());
    self::assertEquals('OK', $data['status']);
    self::assertEquals('abc123', $data['tugboat']['preview_id']);
    self::assertStringContainsString('/progress/abc123/ac123', $data['progress']);

    // The preview was requested against the demo's own base preview.
    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    self::assertEquals('base-umami-id', $payload['base']);
  }

  /**
   * @covers ::launch
   */
  public function testLaunchRejectsUnknownDemo(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('nope is not a valid option');
    Resources::create($this->container)->launch('nope');
  }

  /**
   * @covers ::launch
   */
  public function testLaunchWhenTugboatIsUnreachable(): void {
    $this->config('tugboat.settings')->set('repository_id', 'brokenrepo')->save();

    $this->expectException(ServiceUnavailableHttpException::class);
    Resources::create($this->container)->launch('oneclickdemo_umami');
  }

  /**
   * @covers \Drupal\simplytest_ocd\OneClickDemoPluginManager::__construct
   */
  public function testPluginManagerDefinitions(): void {
    $manager = $this->container->get('plugin.manager.oneclickdemo');

    $definition = $manager->getDefinition('oneclickdemo_umami');
    self::assertEquals('umami', $definition['base_preview_name']);
    self::assertEquals('Umami', (string) $definition['title']);

    self::assertTrue($manager->hasDefinition('oneclickdemo_commerce'));
    self::assertFalse($manager->hasDefinition('nope'));
  }

}
