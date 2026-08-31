<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\CoreVersionManager
 */
final class CoreVersionManagerTest extends KernelTestBase {

  protected static $modules = [
    'simplytest_projects'
  ];

  /**
   * @var list<\Psr\Http\Message\RequestInterface>
   */
  protected array $requests = [];

  /**
   * @var \Drupal\simplytest_projects\CoreVersionManager
   */
  private $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->sut = $this->container->get('simplytest_projects.core_version_manager');
  }

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register(self::class, self::class)
      ->addTag('http_client_middleware');
    $container->set(self::class, $this);
  }

  public function __invoke(): \Closure {
    return fn() => function (RequestInterface $request): PromiseInterface {
      $this->requests[] = $request;
      $uri = $request->getUri();
      $matches = [];
      if ($uri->getHost() === 'updates.drupal.org' && preg_match('#^/release-history/drupal/(current|7\.x)$#', $uri->getPath(), $matches) === 1) {
        // Like the real server: a 304 when the client sends the timestamp it
        // was last handed, since the fixture never changes.
        if ($request->hasHeader('If-Modified-Since')) {
          return new FulfilledPromise(new Response(304, [], ''));
        }
        $fixture = file_get_contents(__DIR__ . "/../../fixtures/release-history/{$matches[1]}/drupal.xml");
        return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], $fixture));
      }

      throw new \RuntimeException("Mocked request tried to escape: {$request->getMethod()} {$request->getUri()}");
    };
  }

  /**
   * Core release data comes from updates.drupal.org, conditionally.
   *
   * @covers ::updateData
   */
  public function testConditionalRequests(): void {
    $this->sut->updateData(8);
    self::assertCount(1, $this->requests);
    // The "current" channel carries every Drupal 8+ major in one document.
    self::assertNotEmpty($this->sut->getVersions(9));
    self::assertNotEmpty($this->sut->getVersions(10));
    self::assertNotEmpty($this->sut->getVersions(11));

    // A later call for another major short-circuits on the 304.
    $this->sut->updateData(11);
    self::assertCount(2, $this->requests);
    self::assertNotEmpty($this->sut->getVersions(11));

    // The If-Modified-Since state is scoped to this consumer, so the project
    // version manager reading the same feed cannot starve it.
    self::assertNotNull(
      $this->container->get('state')->get('release_history_last_modified:drupal:current:core_versions')
    );
  }

  /**
   * @dataProvider coreVersionData
   * @covers ::updateData
   * @covers ::getVersions
   *
   * @param int $major_version
   *   The test major version.
   * @param int $expected_count
   *   The expected release count.
   * @param array $expected_result_sample
   *   The expected release data sample.
   *
   * @throws \Exception
   */
  public function testReleaseData(int $major_version, int $expected_count, array $expected_result_sample): void {
    $this->sut->updateData($major_version);
    $results = $this->sut->getVersions($major_version);
    $this->assertCount($expected_count, $results);
    // NOTE: We do the array map because assertContains performs a strict check
    // and strict checks against objects always fail if they are not literally
    // the same object.
    $this->assertContains($expected_result_sample, array_map(static fn(object $result) => (array) $result, $results));
  }

  public function coreVersionData(): \Generator {
    yield [9, 2, [
      'version' => '9.4.0',
      'major' => '9',
      'minor' => '4',
      'patch' => '0',
      'extra' => null,
      'vcs_label' => '9.4.0',
      'insecure' => '1',
    ]];
    yield [7, 3, [
      'version' => '7.95',
      'major' => '7',
      'minor' => '95',
      'patch' => null,
      'extra' => null,
      'vcs_label' => '7.95',
      'insecure' => '1',
    ]];
    yield [10, 3, [
      'version' => '10.3.x-dev',
      'major' => '10',
      'minor' => '3',
      'patch' => NULL,
      'extra' => 'dev',
      'vcs_label' => '10.3.x',
      'insecure' => '0',
    ]];
    yield [8, 2, [
      'version' => '8.0.0-rc4',
      'major' => '8',
      'minor' => '0',
      'patch' => '0',
      'extra' => 'rc4',
      'vcs_label' => '8.0.0-rc4',
      'insecure' => '0',
    ]];
  }

  public function testGetWithCompatibility() {
    $this->sut->updateData(7);
    $this->sut->updateData(8);

    $this->assertCount(3, $this->sut->getWithCompatibility('7.x'));
    $this->assertCount(2, $this->sut->getWithCompatibility('^11'));
    $this->assertCount(1, $this->sut->getWithCompatibility('^10.6'));
    $this->assertCount(0, $this->sut->getWithCompatibility('^12.0'));
    $this->assertCount(2, $this->sut->getWithCompatibility('^9'));
  }

}
