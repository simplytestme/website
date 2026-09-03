<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_launch\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_launch\Controller\SimplyTestLaunch;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * @group simplytest
 * @group simplytest_launch
 *
 * @coversDefaultClass \Drupal\simplytest_launch\Controller\SimplyTestLaunch
 */
final class LaunchControllerTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_ocd',
    'simplytest_tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_launch',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);
    $this->installConfig(['simplytest_launch']);

    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();

    // The core release every launch below asks for.
    $this->container->get('database')->insert(CoreVersionManager::TABLE_NAME)
      ->fields([
        'version' => '9.3.2',
        'major' => 9,
        'minor' => 3,
        'patch' => 2,
        'extra' => '',
        'vcs_label' => '9.3.2',
        'insecure' => 0,
      ])
      ->execute();
  }

  /**
   * @covers ::configure
   */
  public function testConfigure(): void {
    $url = Url::fromRoute('simplytest_launch.configure', [], [
      'query' => ['launcher' => 'custom-launcher'],
    ]);
    $response = $this->handle(Request::create($url->toString(), 'GET'));

    self::assertEquals(200, $response->getStatusCode());
    self::assertStringContainsString('launcher_mount', (string) $response->getContent());
  }

  /**
   * A project that is not stored yet is fetched before redirecting.
   *
   * @covers ::projectSelector
   */
  public function testProjectSelectorFetchesUnknownProject(): void {
    $response = $this->handleRedirect($this->selectorRequest('token', '8.x-1.9'));

    self::assertEquals(302, $response->getStatusCode());
    self::assertStringContainsString('/configure', $response->getTargetUrl());
    self::assertStringContainsString('project=token', urldecode($response->getTargetUrl()));
    self::assertStringContainsString('version=8.x-1.9', urldecode($response->getTargetUrl()));

    $storage = $this->container->get('entity_type.manager')->getStorage('simplytest_project');
    self::assertCount(1, $storage->loadByProperties(['shortname' => 'token']));
  }

  /**
   * A branch shorthand is expanded to the matching dev release.
   *
   * @covers ::projectSelector
   */
  public function testProjectSelectorExpandsBranchToDev(): void {
    $response = $this->handleRedirect($this->selectorRequest('token', '8.x-1.x'));

    self::assertStringContainsString('version=8.x-1.x-dev', urldecode($response->getTargetUrl()));
  }

  /**
   * A known project with an unknown release refreshes its release history.
   *
   * @covers ::projectSelector
   */
  public function testProjectSelectorRefreshesMissingRelease(): void {
    // Store the project without any release data.
    $this->container->get('database')->insert('simplytest_project')
      ->fields([
        'title' => 'Token',
        'shortname' => 'token',
        'sandbox' => 0,
        'type' => ProjectTypes::MODULE,
        'timestamp' => 0,
        'usage' => 0,
      ])
      ->execute();

    $version_manager = $this->container->get('simplytest_projects.project_version_manager');
    self::assertEmpty($version_manager->getAllReleases('token'));

    $response = $this->handle($this->selectorRequest('token', '8.x-1.9'));

    self::assertEquals(302, $response->getStatusCode());
    self::assertNotEmpty($version_manager->getAllReleases('token'));
  }

  /**
   * The redirect carries the cache metadata the launcher depends on.
   *
   * @covers ::projectSelector
   */
  public function testProjectSelectorCacheMetadata(): void {
    $this->createProject('token');
    $response = $this->handleRedirect($this->selectorRequest('token', '8.x-1.9'));

    self::assertContains('project_versions:token', $response->getCacheableMetadata()->getCacheTags());
    self::assertContains('url.query_args', $response->getCacheableMetadata()->getCacheContexts());
  }

  /**
   * Extra query parameters are carried through to the configure page.
   *
   * @covers ::projectSelector
   */
  public function testProjectSelectorForwardsQueryParameters(): void {
    $this->createProject('token');

    $url = Url::fromRoute('simplytest_launch.project_selector', [
      'project' => 'token',
      'version' => '8.x-1.9',
    ], ['query' => ['install_profile' => 'umami']]);
    $response = $this->handleRedirect(Request::create($url->toString(), 'GET'));

    self::assertStringContainsString('install_profile=umami', urldecode($response->getTargetUrl()));
  }

  /**
   * @covers ::launchProject
   */
  public function testLaunchProject(): void {
    $this->createProject('token');
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');

    $response = $this->handle($this->launchRequest([
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => FALSE,
        'version' => '8.x-1.9',
      ],
      'drupalVersion' => '9.3.2',
      'installProfile' => 'demo_umami',
      'manualInstall' => '0',
    ]));

    self::assertEquals(200, $response->getStatusCode(), (string) $response->getContent());
    $data = Json::decode((string) $response->getContent());
    self::assertEquals('OK', $data['status']);
    self::assertEquals('abc123', $data['tugboat']['preview_id']);
    self::assertStringContainsString('/progress/abc123/ac123', $data['progress']);
  }

  /**
   * Invalid submissions come back as a 422 listing every violation.
   *
   * @covers ::launchProject
   * @covers \Drupal\simplytest_launch\EventSubscriber\UnprocessableHttpExceptionSubscriber::on4xx
   */
  public function testLaunchProjectWithInvalidSubmission(): void {
    $response = $this->handle($this->launchRequest([
      'project' => ['shortname' => '', 'version' => ''],
      'drupalVersion' => '',
      'installProfile' => '',
      'manualInstall' => '0',
    ]));

    self::assertEquals(422, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent());
    self::assertEquals('Unprocessable Entity: validation failed.', $data['message']);
    self::assertContains('project.shortname: This value should not be blank.', $data['errors']);
    self::assertContains('drupalVersion: This value should not be blank.', $data['errors']);
  }

  /**
   * A version that is not a known release is rejected.
   *
   * @covers ::launchProject
   */
  public function testLaunchProjectWithUnknownVersion(): void {
    $this->createProject('token');
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');

    $response = $this->handle($this->launchRequest([
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => FALSE,
        'version' => '8.x-99.0',
      ],
      'drupalVersion' => '9.3.2',
      'installProfile' => 'demo_umami',
      'manualInstall' => '0',
    ]));

    self::assertEquals(422, $response->getStatusCode());
    self::assertStringContainsString('8.x-99.0', (string) $response->getContent());
  }

  /**
   * Only a known core release may be launched.
   *
   * The form only offers stored releases, but the endpoint takes any JSON, and
   * the value would otherwise reach the sandbox build and the public
   * statistics untouched.
   *
   * @covers ::launchProject
   * @covers \Drupal\simplytest_launch\Plugin\Validation\Constraint\CoreVersionConstraintValidator::validate
   */
  public function testLaunchProjectWithUnknownCoreVersion(): void {
    $response = $this->handle($this->launchRequest([
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => FALSE,
        'version' => '8.x-1.9',
      ],
      'drupalVersion' => 'not a real core version',
      'installProfile' => 'standard',
      'manualInstall' => '0',
    ]));

    self::assertEquals(422, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent());
    self::assertContains(
      'drupalVersion: There is no Drupal core release with the version not a real core version.',
      $data['errors']
    );
  }

  /**
   * Only the install profiles the form offers may be launched.
   *
   * @covers ::launchProject
   */
  public function testLaunchProjectWithUnknownInstallProfile(): void {
    $response = $this->handle($this->launchRequest([
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => FALSE,
        'version' => '8.x-1.9',
      ],
      'drupalVersion' => '9.3.2',
      'installProfile' => 'buy pills at example dot com',
      'manualInstall' => '0',
    ]));

    self::assertEquals(422, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent());
    self::assertContains(
      'installProfile: The install profile must be one of standard, minimal, demo_umami.',
      $data['errors']
    );
  }

  /**
   * A failure reaching Tugboat surfaces as a 503.
   *
   * The controller is called directly rather than through the kernel: a 5xx is
   * logged by core's exception subscriber, and KernelTestBase turns any logged
   * error into a test failure.
   *
   * @covers ::launchProject
   */
  public function testLaunchProjectWhenTugboatIsUnreachable(): void {
    $this->createProject('token');
    $this->container->get('simplytest_projects.project_version_manager')->updateData('token');
    // This repository ID makes the Tugboat API fail in the mocked middleware.
    $this->config('tugboat.settings')->set('repository_id', 'brokenrepo')->save();

    $controller = SimplyTestLaunch::create($this->container);

    $this->expectException(ServiceUnavailableHttpException::class);
    $this->expectExceptionMessage('Tugboat is unreachable');
    $controller->launchProject($this->launchRequest([
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => FALSE,
        'version' => '8.x-1.9',
      ],
      'drupalVersion' => '9.3.2',
      'installProfile' => 'demo_umami',
      'manualInstall' => '0',
    ]));
  }

  /**
   * A submission that is not an array at all is rejected, not fatal.
   *
   * @covers ::launchProject
   */
  public function testLaunchProjectWithUnusableSubmission(): void {
    $controller = SimplyTestLaunch::create($this->container);

    $this->expectException(ServiceUnavailableHttpException::class);
    $request = Request::create(
      Url::fromRoute('simplytest_launch.project_launcher')->toString(),
      'POST',
      [],
      [],
      [],
      [],
      Json::encode('not a submission'),
    );
    $controller->launchProject($request);
  }

  private function selectorRequest(string $project, string $version): Request {
    $url = Url::fromRoute('simplytest_launch.project_selector', [
      'project' => $project,
      'version' => $version,
    ]);
    return Request::create($url->toString(), 'GET');
  }

  /**
   * @param array<string, mixed> $submission
   */
  private function launchRequest(array $submission): Request {
    $url = Url::fromRoute('simplytest_launch.project_launcher');
    $request = Request::create($url->toString(), 'POST', [], [], [], [], Json::encode($submission));
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Accept', 'application/json');
    return $request;
  }

  private function handle(Request $request): Response {
    return $this->container->get('http_kernel')->handle($request);
  }

  private function handleRedirect(Request $request): LocalRedirectResponse {
    $response = $this->handle($request);
    self::assertInstanceOf(LocalRedirectResponse::class, $response);
    return $response;
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

}
