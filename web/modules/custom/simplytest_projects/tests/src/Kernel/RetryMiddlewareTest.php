<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\Http\Middleware\RetryMiddleware;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the RetryMiddleware.
 *
 * @group simplytest
 * @group simplytest_project
 *
 * @coversDefaultClass \Drupal\simplytest_projects\Http\Middleware\RetryMiddleware
 */
final class RetryMiddlewareTest extends KernelTestBase
{

  protected static $modules = [
    'simplytest_projects',
  ];

  /**
   * @var list<\Psr\Http\Message\RequestInterface>
   */
  protected array $requests = [];

  /**
   * @var int
   */
  protected int $attempt = 0;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void
  {
    parent::register($container);
    // Register self as a middleware to mock responses.
    // This runs *after* the RetryMiddleware.
    $container->register(self::class, self::class)
      ->addTag('http_client_middleware', ['priority' => -100]);
    $container->set(self::class, $this);
  }

  /**
   * Mock middleware invoker.
   */
  public function __invoke(): \Closure
  {
    return function () {
      return function (RequestInterface $request): PromiseInterface {
        $this->requests[] = $request;
        $this->attempt++;

        // Simulate 429 on first two attempts, then 200.
        if ($request->getUri()->getPath() === '/429-test') {
          if ($this->attempt < 3) {
            return new FulfilledPromise(new Response(429, ['Retry-After' => '1'], 'Too Many Requests'));
          }
          return new FulfilledPromise(new Response(200, [], 'OK'));
        }

        // Simulate 503 on first two attempts, then 200.
        if ($request->getUri()->getPath() === '/503-test') {
          if ($this->attempt < 3) {
            return new FulfilledPromise(new Response(503, [], 'Service Unavailable'));
          }
          return new FulfilledPromise(new Response(200, [], 'OK'));
        }

        return new FulfilledPromise(new Response(404, [], 'Not Found'));
      };
    };
  }

  public function testRetryOn429(): void
  {
    $client = $this->container->get('http_client');
    $this->attempt = 0;
    $response = $client->request('GET', 'http://example.com/429-test');

    // Should have retried twice and succeeded on the 3rd attempt.
    self::assertEquals(200, $response->getStatusCode());
    self::assertEquals('OK', (string) $response->getBody());
    self::assertCount(3, $this->requests);
  }

  public function testRetryOn503(): void
  {
    $client = $this->container->get('http_client');
    $this->attempt = 0;
    $response = $client->request('GET', 'http://example.com/503-test');

    self::assertEquals(200, $response->getStatusCode());
    self::assertEquals('OK', (string) $response->getBody());
    self::assertCount(3, $this->requests);
  }
}
