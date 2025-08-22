<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Traits;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 */
trait MockedReleaseHttpClientTrait {

  /**
   * @var list<\Psr\Http\Message\RequestInterface>
   */
  protected array $requests = [];

  private static function registerWithContainer(ContainerBuilder $container, object $instance): void {
    $container->register(static::class, static::class)
      ->addTag('http_client_middleware');
    $container->set(static::class, $instance);
  }

  public function __invoke(): \Closure {
    return function () {
      return function (RequestInterface $request): PromiseInterface {
        $this->requests[] = $request;

        $uri = (string) $request->getUri();

        if ($uri === 'https://updates.drupal.org/release-history/pathauto/current') {
          if ($request->hasHeader('If-Modified-Since')) {
            return self::notModifiedResponse();
          }
          $fixture = file_get_contents(__DIR__ . '/../../fixtures/release-history/current/pathauto.xml');
          return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], $fixture));
        }

        if ($uri === 'https://updates.drupal.org/release-history/pathauto/7.x') {
          if ($request->hasHeader('If-Modified-Since')) {
            return self::notModifiedResponse();
          }
          $fixture = file_get_contents(__DIR__ . '/../../fixtures/release-history/7.x/pathauto.xml');
          return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], $fixture));
        }

        if ($uri === 'https://updates.drupal.org/release-history/drupalbin/current') {
          $fixture = file_get_contents(__DIR__ . '/../../fixtures/release-history/current/drupalbin.xml');
          return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], $fixture));
        }

        if ($uri === 'https://updates.drupal.org/release-history/drupalbin/7.x') {
          $fixture = file_get_contents(__DIR__ . '/../../fixtures/release-history/7.x/drupalbin.xml');
          return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], $fixture));
        }

        if ($uri === 'https://updates.drupal.org/release-history/bootstrap/current') {
          if ($request->hasHeader('If-Modified-Since')) {
            return self::notModifiedResponse();
          }
          $fixture = file_get_contents(__DIR__ . '/../../fixtures/release-history/current/bootstrap.xml');
          return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], $fixture));
        }
        if ($uri === 'https://updates.drupal.org/release-history/bootstrap/7.x') {
          // Not mocked, skip.
          return self::notModifiedResponse();
        }

        throw new \RuntimeException('Mocked request tried to escape: ' . $request->getUri());
      };
    };
  }

  private static function notModifiedResponse(): FulfilledPromise {
    return new FulfilledPromise(new Response(304, [], ''));
  }

}
