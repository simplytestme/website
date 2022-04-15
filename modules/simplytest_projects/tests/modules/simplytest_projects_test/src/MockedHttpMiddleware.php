<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects_test;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

final class MockedHttpMiddleware {

  public function __invoke() {
    return static function (callable $handler) {
      return static function (RequestInterface $request, array $options) use ($handler) {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        if ($path === '/api-d7/node.json' && $query === 'field_project_machine_name=token') {
          return new FulfilledPromise(
            new Response(200, [], file_get_contents(__DIR__ . '/../../../fixtures/node/field_project_machine_name_token.json'))
          );
        }

        throw new \InvalidArgumentException("No response mocked for '{$request->getUri()}'");
      };
    };
  }

}
