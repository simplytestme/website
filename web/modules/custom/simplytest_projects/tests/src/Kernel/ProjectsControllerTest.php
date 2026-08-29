<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\Controller\SimplyTestProjects
 */
final class ProjectsControllerTest extends KernelTestBase {

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

  /**
   * @covers ::autocompleteProjects
   */
  public function testAutocompleteFindsStoredProject(): void {
    SimplytestProject::create([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
      'usage' => 500,
    ])->save();

    $data = $this->requestJson(Url::fromRoute('simplytest_projects.projects', [], [
      'query' => ['string' => 'tok'],
    ]));
    self::assertEquals('token', $data[0]['shortname']);
  }

  /**
   * Nothing stored locally falls back to fetching the project from Drupal.org.
   *
   * @covers ::autocompleteProjects
   */
  public function testAutocompleteFallsBackToFetch(): void {
    $data = $this->requestJson(Url::fromRoute('simplytest_projects.projects', [], [
      'query' => ['string' => 'token'],
    ]));

    self::assertCount(1, $data);
    self::assertEquals('token', $data[0]['shortname']);
    // The fallback strips the fields the autocomplete has no use for.
    self::assertArrayNotHasKey('creator', $data[0]);
    self::assertArrayNotHasKey('usage', $data[0]);
    self::assertArrayNotHasKey('sandbox', $data[0]);
  }

  /**
   * A search string with spaces is retried with underscores.
   *
   * @covers ::autocompleteProjects
   */
  public function testAutocompleteConvertsSpaces(): void {
    $data = $this->requestJson(Url::fromRoute('simplytest_projects.projects', [], [
      'query' => ['string' => 'admin toolbar'],
    ]));
    self::assertEquals('admin_toolbar', $data[0]['shortname']);
  }

  /**
   * @covers ::autocompleteProjects
   */
  public function testAutocompleteWithoutSearchString(): void {
    self::assertEquals([], $this->requestJson(Url::fromRoute('simplytest_projects.projects')));
  }

  /**
   * @covers ::autocompleteProjects
   */
  public function testAutocompleteWithNoMatchAtAll(): void {
    self::assertEquals([], $this->requestJson(Url::fromRoute('simplytest_projects.projects', [], [
      'query' => ['string' => 'notaproject'],
    ])));
  }

  /**
   * @covers ::projectVersions
   */
  public function testProjectVersions(): void {
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');

    $url = Url::fromRoute('simplytest_projects.versions', ['project' => 'token']);
    $response = $this->handle(Request::create($url->toString(), 'GET'));
    self::assertEquals(200, $response->getStatusCode());

    $data = Json::decode((string) $response->getContent());
    self::assertArrayHasKey('latest', $data['list']);
    self::assertArrayHasKey('branches', $data['list']);
    self::assertArrayHasKey('core', $data['list']);
    self::assertNotEmpty($data['list']['latest']);
  }

  /**
   * @covers ::compatibleProjectVersions
   */
  public function testCompatibleProjectVersions(): void {
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');

    $url = Url::fromRoute('simplytest_projects.compatible_versions', [
      'project' => 'token',
      'core_version' => '9.5.0',
    ]);
    $data = Json::decode((string) $this->handle(Request::create($url->toString(), 'GET'))->getContent());

    self::assertNotEmpty($data['list']['latest']);
    foreach ($data['list']['core'] as $core_data) {
      self::assertNotEquals('Drupal 7', $core_data['label']);
    }
  }

  /**
   * @covers ::compatibleCoreVersions
   */
  public function testCompatibleCoreVersions(): void {
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');
    $this->container->get('simplytest_projects.core_version_manager')->updateData(9);

    $url = Url::fromRoute('simplytest_projects.compatible_core_versions', [
      'project' => 'token',
      'version' => '8.x-1.9',
    ]);
    $response = $this->handle($this->jsonRequest($url));

    self::assertEquals(200, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent());
    self::assertNotEmpty($data['list']);
    self::assertInstanceOf(CacheableJsonResponse::class, $response);
    self::assertEqualsCanonicalizing(
      ['core_versions', 'core_compatibility:token:8.x-1.9', 'http_response'],
      $response->getCacheableMetadata()->getCacheTags(),
    );
  }

  /**
   * @covers ::compatibleCoreVersions
   */
  public function testCompatibleCoreVersionsForUnknownRelease(): void {
    $url = Url::fromRoute('simplytest_projects.compatible_core_versions', [
      'project' => 'token',
      'version' => '8.x-99.0',
    ]);
    $response = $this->handle($this->jsonRequest($url));

    self::assertEquals(404, $response->getStatusCode());
    self::assertEquals(['notfound'], Json::decode((string) $response->getContent()));
  }

  /**
   * @covers ::coreVersions
   */
  public function testCoreVersions(): void {
    $this->container->get('simplytest_projects.core_version_manager')->updateData(9);

    $url = Url::fromRoute('simplytest_projects.core_versions', ['major_version' => 9]);
    $response = $this->handle($this->jsonRequest($url));

    self::assertEquals(200, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent());
    self::assertNotEmpty($data['list']);
  }

  private function jsonRequest(Url $url): Request {
    $request = Request::create($url->toString(), 'GET');
    $request->headers->set('Accept', 'application/json');
    return $request;
  }

  private function handle(Request $request): Response {
    return $this->container->get('http_kernel')->handle($request);
  }

  /**
   * @return array<mixed>
   */
  private function requestJson(Url $url): array {
    $response = $this->handle(Request::create($url->toString(), 'GET'));
    self::assertEquals(200, $response->getStatusCode());
    return Json::decode((string) $response->getContent());
  }

}
