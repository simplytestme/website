<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects_test;

use Drupal\Component\Serialization\Json;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

final class MockedHttpMiddleware {

  private StateInterface $state;

  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  public function __invoke() {
    return function (callable $handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        if ($path === '/api-d7/node.json' && $query === 'field_project_machine_name=token') {
          return new FulfilledPromise(
            new Response(200, [], file_get_contents(__DIR__ . '/../../../fixtures/node/field_project_machine_name_token.json'))
          );
        }

        $uri = (string) $request->getUri();
        $release_matches = [];
        if (preg_match('/https:\/\/updates.drupal.org\/release-history\/(.*)\/(.*)/', $uri, $release_matches) === 1) {
          return new FulfilledPromise(
            new Response(200, [], file_get_contents(__DIR__ . '/../../../fixtures/release-history/' . $release_matches[2] . '/' . $release_matches[1]. '.xml'))
          );
        }

        if ($uri === 'https://api.tugboat.qa/v3/repos/kerneltestrepo/previews') {
          $this->state->set($uri, (string) $request->getBody());
          return new FulfilledPromise(
            new Response(200, [], Json::encode([
              [
                'provider_label' => 'base-drupal9',
                'id' => 'base-drupal9-id',
              ],
            ]))
          );
        }
        if ($uri === 'https://api.tugboat.qa/v3/previews' && $request->getMethod() === 'POST') {
          $this->state->set($uri, Json::decode((string) $request->getBody()));
          return new FulfilledPromise(
            new Response(200, [
              'Content-Location' => 'https://api.tugboat.qa/v3/previews/abc123'
            ], Json::encode([
              'preview' => 'abc123',
              'job' => 'ac123',
            ]))
          );
        }

        throw new \InvalidArgumentException("No response mocked for '{$request->getUri()}'");
      };
    };
  }

}
