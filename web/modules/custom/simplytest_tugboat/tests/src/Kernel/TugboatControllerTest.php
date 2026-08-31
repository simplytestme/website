<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_tugboat\Controller\SimplytestTugboatController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\Controller\SimplytestTugboatController
 */
final class TugboatControllerTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  private SimplytestTugboatController $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);

    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();

    $this->sut = SimplytestTugboatController::create($this->container);
  }

  /**
   * @covers ::progress
   */
  public function testProgressPage(): void {
    $url = Url::fromRoute('simplytest_tugboat.progress', [
      'instance_id' => 'abc123',
      'job_id' => 'ac123',
    ]);
    $response = $this->container->get('http_kernel')->handle(Request::create($url->toString(), 'GET'));

    self::assertEquals(200, $response->getStatusCode());
    self::assertStringContainsString('progress_mount', (string) $response->getContent());
  }

  /**
   * The build array carries the IDs the front end needs.
   *
   * @covers ::progress
   */
  public function testProgressBuild(): void {
    $build = $this->sut->progress(Request::create('/'), 'abc123', 'ac123');

    $settings = $build['mount']['#attached']['drupalSettings'];
    self::assertEquals('abc123', $settings['instanceId']);
    self::assertEquals('ac123', $settings['jobId']);
    self::assertStringContainsString('/tugboat/status/abc123/ac123', $settings['stateUrl']);
  }

  /**
   * A finished preview reports as ready, cacheable, and fully progressed.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateForFinishedPreview(): void {
    $response = $this->sut->instanceState('abc123', 'finished-job');

    self::assertInstanceOf(CacheableJsonResponse::class, $response);
    $data = Json::decode((string) $response->getContent());
    self::assertEquals('preview', $data['type']);
    self::assertEquals('ready', $data['state']);
    self::assertEquals('https://preview.tugboatqa.com/abc123', $data['url']);
    // Three of the five markers are present in the mocked log.
    self::assertEquals(60, $data['progress']);
  }

  /**
   * Git noise is stripped out of the log before it reaches the front end.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateFiltersGitNoise(): void {
    $data = Json::decode((string) $this->sut->instanceState('abc123', 'finished-job')->getContent());

    $messages = array_column($data['logs'], 'message');
    self::assertContains('Cloning repository', $messages);
    self::assertNotContains(' * [new branch] main -> origin/main', $messages);
    self::assertNotContains(' * [new tag] 1.0.0', $messages);
    self::assertNotContains('branch new (next fetch will store in remotes/origin)', $messages);
  }

  /**
   * A suspended preview reports the state it was suspended at.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateForSuspendedPreview(): void {
    $data = Json::decode((string) $this->sut->instanceState('abc123', 'suspended-job')->getContent());
    self::assertEquals('suspended', $data['state']);
  }

  /**
   * A job that is still building is not cacheable yet.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateForRunningJob(): void {
    $response = $this->sut->instanceState('abc123', 'running-job');

    self::assertNotInstanceOf(CacheableJsonResponse::class, $response);
    self::assertInstanceOf(JsonResponse::class, $response);
    $data = Json::decode((string) $response->getContent());
    self::assertEquals('job', $data['type']);
    self::assertEquals('building', $data['state']);
    self::assertNull($data['url']);
  }

  /**
   * @covers ::instanceState
   */
  public function testInstanceStateForMissingJob(): void {
    $response = $this->sut->instanceState('abc123', 'missing-job');

    self::assertEquals(404, $response->getStatusCode());
    self::assertEquals(
      ['message' => 'Sandbox instance no longer exists'],
      Json::decode((string) $response->getContent()),
    );
  }

  /**
   * An unrecognized job type is a bug, not a state to render.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateForUnknownJobType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unexpected job type');
    $this->sut->instanceState('abc123', 'bogus-job');
  }

  /**
   * Upstream errors become a 502 for the poller instead of a raw 500.
   *
   * The progress page polls this endpoint; an exception page would be parsed
   * as JSON and polled into forever. A clean 502 lets the frontend back off.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateMapsOtherClientErrors(): void {
    $response = $this->sut->instanceState('abc123', 'forbidden-job');

    self::assertEquals(502, $response->getStatusCode());
    self::assertEquals(
      ['message' => 'Unable to fetch the sandbox status.'],
      Json::decode((string) $response->getContent()),
    );
  }

  /**
   * A network failure reaching Tugboat is also a 502, not an exception page.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateMapsNetworkFailure(): void {
    $response = $this->sut->instanceState('abc123', 'unreachable-job');

    self::assertEquals(502, $response->getStatusCode());
    self::assertEquals(
      ['message' => 'Unable to fetch the sandbox status.'],
      Json::decode((string) $response->getContent()),
    );
  }

  /**
   * Duplicate stage markers cannot push progress past 100.
   *
   * @covers ::instanceState
   */
  public function testInstanceStateProgressClampsAtHundred(): void {
    $data = Json::decode((string) $this->sut->instanceState('abc123', 'noisy-job')->getContent());
    self::assertEquals(100, $data['progress']);
  }

  /**
   * A finished preview carries a finite lifetime for browsers and caches.
   *
   * Sandboxes are deleted two hours after creation; a permanently cached
   * "ready" response would keep redirecting users to a dead preview.
   *
   * @covers ::instanceState
   */
  public function testInstanceStatePreviewMaxAgeIsFinite(): void {
    $response = $this->sut->instanceState('abc123', 'finished-job');

    self::assertInstanceOf(CacheableJsonResponse::class, $response);
    self::assertEquals(60, $response->getCacheableMetadata()->getCacheMaxAge());
    self::assertEquals(60, $response->getMaxAge());
  }

}
