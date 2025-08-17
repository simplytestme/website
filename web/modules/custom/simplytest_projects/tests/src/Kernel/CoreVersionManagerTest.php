<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\State\State;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Promise\FulfilledPromise;
use Drupal\simplytest_projects\CoreVersionManager;
use GuzzleHttp\Client;
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
    return function () {
      return function (RequestInterface $request): PromiseInterface {
        $this->requests[] = $request;
        if ($request->getMethod() === 'HEAD' && $request->getUri()->getHost() === 'updates.drupal.org') {
          if ($request->hasHeader('If-Modified-Since')) {
            return new FulfilledPromise(new Response(304, [], ''));
          }
          return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], ''));
        }

        $path = $request->getUri()->getPath();
        if ($path === '/api-d7/node.json') {
          $query = [];
          parse_str($request->getUri()->getQuery(), $query);
          if ($query['type'] === 'project_release' && $query['field_release_project'] === '3060') {
            $version = $query['field_release_version_major'] ?? '';
            $fixture_file = __DIR__ . "/../../fixtures/node/project_release/core-$version.json";
            if (file_exists($fixture_file)) {
              $fixture = file_get_contents($fixture_file);
              if ($query['page'] === '1') {
                $decoded_fixture = \json_decode($fixture, TRUE, 512, JSON_THROW_ON_ERROR);
                // Prevent infinite looping for pagination logic.
                unset($decoded_fixture['next']);
                $fixture = \json_encode($decoded_fixture, JSON_THROW_ON_ERROR);
              }
              return new FulfilledPromise(new Response(200, ['Content-Type' => 'application/json'], $fixture));
            }
          }
        }

        throw new \RuntimeException("Mocked request tried to escape: {$request->getMethod()} {$request->getUri()}");
      };
    };
  }

  public function testPreflightCheck(): void {
    $state = new State(new KeyValueMemoryFactory(), new NullBackend('state'), new NullLockBackend());
    $sut = new CoreVersionManager(
      $this->container->get('database'),
      $this->container->get('http_client'),
      $state
    );
    $sut->updateData(7);
    self::assertCount(3, $this->requests);
    self::assertNotNull($state->get('release_history_last_modified:drupal:7'));
    $last_modified = $state->get('release_history_last_modified:drupal:7');
    $sut->updateData(7);
    self::assertEquals($last_modified, $state->get('release_history_last_modified:drupal:7'));
    self::assertCount(4, $this->requests);
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
    $this->assertGreaterThanOrEqual($expected_count, $results);
    // NOTE: We do the array map because assertContains performs a strict check
    // and strict checks against objects always fail if they are not literally
    // the same object.
    $this->assertContains($expected_result_sample, array_map(static fn(object $result) => (array) $result, $results));
  }

  public function coreVersionData(): \Generator {
    yield [9, 33, [
      'version' => '9.4.0',
      'major' => '9',
      'minor' => '4',
      'patch' => '0',
      'extra' => null,
      'vcs_label' => '9.4.0',
      'insecure' => '1',
    ]];
    yield [7, 9, [
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
  }

  public function testGetWithCompatibility() {
    $this->sut->updateData(7);
    $this->sut->updateData(8);
    $this->sut->updateData(9);

    $this->assertGreaterThanOrEqual(90, $this->sut->getWithCompatibility('7.x'));
    $this->assertCount(0, $this->sut->getWithCompatibility('^10.0'));
    $this->assertGreaterThanOrEqual(33, $this->sut->getWithCompatibility('^9'));
    $this->assertGreaterThanOrEqual(14, $this->sut->getWithCompatibility('^8.9.1'));
    $this->assertGreaterThanOrEqual(200, $this->sut->getWithCompatibility('8.x'));
  }

}
