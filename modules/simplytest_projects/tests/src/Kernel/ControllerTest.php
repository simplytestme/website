<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Symfony\Component\HttpFoundation\Request;

final class ControllerTest extends KernelTestBase implements ServiceModifierInterface {

  protected static $modules = [
    'page_cache',
    'dynamic_page_cache',
    'simplytest_projects'
  ];

  public function alter(ContainerBuilder $container) {
    // Make sure we have debug headers to test.
    $container->setParameter('http.response.debug_cacheability_headers', TRUE);
  }

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
  }

  public function testCoreVersions(): void {
    $this->container->get('simplytest_projects.core_version_manager')->updateData(9);
    $url = Url::fromRoute('simplytest_projects.core_versions', [
      'major_version' => 9,
    ]);
    $request = Request::create($url->toString(), 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $this->container->get('http_kernel')->handle($request);
    assert($response instanceof CacheableJsonResponse);
    $this->assertEquals('core_versions core_versions:9 http_response', $response->headers->get('x-drupal-cache-tags'));
    $this->assertEquals('', $response->headers->get('x-drupal-cache-context'));
    $this->assertEquals('-1 (Permanent)', $response->headers->get('x-drupal-cache-max-age'));
    $data = Json::decode((string) $response->getContent());
    $this->assertGreaterThanOrEqual(33, $data['list']);
  }

}
