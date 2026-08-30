<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects_test;

use Drupal\Component\Serialization\Json;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Answers every outbound HTTP request a kernel test would otherwise make.
 *
 * Requests are matched against the recorded fixtures first. Anything a fixture
 * cannot express — a malformed payload, a project that does not exist, a server
 * that is down — is keyed off a reserved project shortname so a test can ask for
 * the failure it wants without a fixture file per case. See
 * self::PROJECT_EDGE_CASES.
 *
 * An unmatched request throws rather than returning an empty response, so a new
 * call to the network fails loudly instead of silently passing.
 */
final readonly class MockedHttpMiddleware {

  /**
   * Reserved shortnames that produce a specific Drupal.org API failure.
   */
  private const array PROJECT_EDGE_CASES = [
    // The API answers, but knows nothing about the project.
    'notaproject' => 'empty_list',
    // The API answers with something that is not JSON at all.
    'malformed' => 'invalid_json',
    // A project node missing the fields the fetcher depends on.
    'no_title' => 'missing_title',
    'no_type' => 'missing_type',
    'weird_type' => 'unknown_type',
    // A sandbox project, where the creator is scraped out of the URL.
    'sandboxed' => 'sandbox',
    'sandbox_no_url' => 'sandbox_missing_url',
    // Transport level failures.
    'servererror' => 'server_error',
    'notfound' => 'not_found',
  ];

  public function __construct(private StateInterface $state) {
  }

  public function __invoke(): \Closure {
    return fn(callable $handler) => function (RequestInterface $request, array $options) use ($handler) {
      $uri = (string) $request->getUri();
      $path = $request->getUri()->getPath();
      $query = [];
      parse_str($request->getUri()->getQuery(), $query);

      if ($path === '/api-d7/node.json') {
        return $this->handleOrgApi($request, $query);
      }
      if (str_starts_with($uri, 'https://updates.drupal.org/release-history/')) {
        return $this->handleReleaseHistory($request);
      }
      if (str_starts_with($uri, 'https://api.tugboatqa.com/v3/')) {
        return $this->handleTugboat($request);
      }

      throw new \InvalidArgumentException("No response mocked for '{$request->getUri()}'");
    };
  }

  /**
   * Serves the Drupal.org JSON API.
   *
   * @param array<string, string> $query
   */
  private function handleOrgApi(RequestInterface $request, array $query): FulfilledPromise|RejectedPromise {
    // A single project lookup, by machine name.
    if (isset($query['field_project_machine_name'])) {
      return $this->handleProjectLookup($request, $query['field_project_machine_name']);
    }

    $type = $query['type'] ?? '';

    // Drupal core release nodes, used to build the core version list.
    if ($type === 'project_release' && ($query['field_release_project'] ?? '') === '3060') {
      return $this->handleCoreReleases($query);
    }

    // A page of projects, used by the importer.
    if (in_array($type, ['project_module', 'project_theme', 'project_distribution'], TRUE)) {
      return $this->handleProjectListing($type, (int) ($query['page'] ?? 0));
    }

    throw new \InvalidArgumentException("No response mocked for '{$request->getUri()}'");
  }

  private function handleProjectLookup(RequestInterface $request, string $shortname): FulfilledPromise|RejectedPromise {
    $case = self::PROJECT_EDGE_CASES[$shortname] ?? NULL;
    if ($case !== NULL) {
      return $this->projectEdgeCase($request, $shortname, $case);
    }

    $fixture = __DIR__ . "/../../../fixtures/node/field_project_machine_name_$shortname.json";
    if (is_file($fixture)) {
      return new FulfilledPromise(new Response(200, [], file_get_contents($fixture)));
    }

    // Any other project is a well formed module owned by nobody in particular.
    // Tests that only need "a project that exists" get one for free.
    return new FulfilledPromise(new Response(200, [], Json::encode([
      'list' => [
        [
          'title' => ucfirst(str_replace('_', ' ', $shortname)),
          'type' => 'project_module',
          'field_project_type' => 'full',
          'field_project_machine_name' => $shortname,
          'url' => 'https://www.drupal.org/project/' . $shortname,
          'project_usage' => ['8.x' => '100', '9.x' => '50'],
        ],
      ],
    ])));
  }

  private function projectEdgeCase(RequestInterface $request, string $shortname, string $case): FulfilledPromise|RejectedPromise {
    $node = [
      'title' => ucfirst($shortname),
      'type' => 'project_module',
      'field_project_type' => 'full',
      'field_project_machine_name' => $shortname,
      'url' => 'https://www.drupal.org/project/' . $shortname,
    ];

    return match ($case) {
      'empty_list' => new FulfilledPromise(new Response(200, [], Json::encode(['list' => []]))),
      'invalid_json' => new FulfilledPromise(new Response(200, [], 'this is not json')),
      'missing_title' => new FulfilledPromise(new Response(200, [], Json::encode([
        'list' => [array_diff_key($node, ['title' => NULL])],
      ]))),
      'missing_type' => new FulfilledPromise(new Response(200, [], Json::encode([
        'list' => [array_diff_key($node, ['type' => NULL])],
      ]))),
      'unknown_type' => new FulfilledPromise(new Response(200, [], Json::encode([
        'list' => [['type' => 'project_something_else'] + $node],
      ]))),
      'sandbox' => new FulfilledPromise(new Response(200, [], Json::encode([
        'list' => [
          [
            'field_project_type' => 'sandbox',
            'url' => 'https://www.drupal.org/sandbox/someuser/2812851',
          ] + $node,
        ],
      ]))),
      'sandbox_missing_url' => new FulfilledPromise(new Response(200, [], Json::encode([
        'list' => [
          array_diff_key(['field_project_type' => 'sandbox'] + $node, ['url' => NULL]),
        ],
      ]))),
      'server_error' => new RejectedPromise(new ServerException(
        'Drupal.org is down',
        $request,
        new Response(503, [], 'Service unavailable'),
      )),
      'not_found' => new RejectedPromise(new ClientException(
        'Not found',
        $request,
        new Response(404, [], 'Not found'),
      )),
      default => throw new \InvalidArgumentException("Unhandled project edge case '$case'"),
    };
  }

  /**
   * @param array<string, string> $query
   */
  private function handleCoreReleases(array $query): FulfilledPromise {
    $major = $query['field_release_version_major'] ?? '';
    $fixture_file = __DIR__ . "/../../../fixtures/node/project_release/core-$major.json";
    if (!is_file($fixture_file)) {
      return new FulfilledPromise(new Response(200, [], Json::encode(['list' => []])));
    }

    $fixture = Json::decode(file_get_contents($fixture_file));
    // The fixtures carry a `next` link. Drop it after the first page so the
    // pagination loop in CoreVersionManager terminates.
    if (($query['page'] ?? '0') !== '0') {
      unset($fixture['next']);
    }
    return new FulfilledPromise(new Response(200, ['Content-Type' => 'application/json'], Json::encode($fixture)));
  }

  private function handleProjectListing(string $type, int $page): FulfilledPromise {
    // Three zero-based pages of results (`last` names page 2), so the
    // importer builds a batch with a real operation for every page.
    $base = 'https://www.drupal.org/api-d7/node?type=' . $type;
    $list = [];
    foreach (range(1, 3) as $index) {
      $shortname = str_replace('project_', '', $type) . "_{$page}_{$index}";
      $list[] = [
        'title' => ucfirst(str_replace('_', ' ', $shortname)),
        'type' => $type,
        'field_project_machine_name' => $shortname,
        'field_project_type' => 'full',
        'author' => ['name' => 'someuser'],
      ];
    }
    return new FulfilledPromise(new Response(200, [], Json::encode([
      'self' => "$base&page=$page",
      'first' => "$base&page=0",
      'last' => "$base&page=2",
      'list' => $list,
    ])));
  }

  private function handleReleaseHistory(RequestInterface $request): FulfilledPromise {
    $matches = [];
    if (preg_match('#https://updates\.drupal\.org/release-history/([^/]+)/(.+)#', (string) $request->getUri(), $matches) !== 1) {
      throw new \InvalidArgumentException("No response mocked for '{$request->getUri()}'");
    }
    [, $project, $channel] = $matches;

    if ($request->getMethod() === 'HEAD') {
      // The core version manager uses a HEAD request to decide whether a full
      // API pull is worth doing.
      if ($request->hasHeader('If-Modified-Since')) {
        return new FulfilledPromise(new Response(304, [], ''));
      }
      return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], ''));
    }

    $fixture = __DIR__ . "/../../../fixtures/release-history/$channel/$project.xml";
    if (!is_file($fixture)) {
      // Drupal.org answers with a 200 and this sentinel body, not a 404.
      return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], "No release history was found for the requested project ($project)."));
    }
    return new FulfilledPromise(new Response(200, ['Last-Modified' => 'Wed, 21 Apr 2021 00:36:14 GMT'], file_get_contents($fixture)));
  }

  private function handleTugboat(RequestInterface $request): FulfilledPromise|RejectedPromise {
    $uri = (string) $request->getUri();

    // A reserved repository ID, so a test can make Tugboat unreachable.
    if (str_contains($uri, '/repos/brokenrepo/')) {
      return new RejectedPromise(new ServerException(
        'Tugboat is unreachable',
        $request,
        new Response(500, [], 'Internal server error'),
      ));
    }

    // The repository segment must be present: a test that never configured
    // tugboat.settings.repository_id requests `repos//previews`, and that has
    // to fail loudly rather than return the base previews.
    if (preg_match('#/v3/repos/[^/]+/previews$#', $uri) === 1 && $request->getMethod() === 'GET') {
      $this->state->set($uri, (string) $request->getBody());
      return new FulfilledPromise(new Response(200, [], Json::encode([
        ['provider_label' => 'base-drupal7', 'id' => 'base-drupal7-id'],
        ['provider_label' => 'base-drupal9', 'id' => 'base-drupal9-id'],
        ['provider_label' => 'base-drupal10', 'id' => 'base-drupal10-id'],
        ['provider_label' => 'base-umami', 'id' => 'base-umami-id'],
        ['provider_label' => 'base-commerce', 'id' => 'base-commerce-id'],
        ['provider_label' => 'base-starshot', 'id' => 'base-starshot-id'],
      ])));
    }

    if ($uri === 'https://api.tugboatqa.com/v3/previews' && $request->getMethod() === 'POST') {
      $this->state->set($uri, Json::decode((string) $request->getBody()));
      return new FulfilledPromise(new Response(
        200,
        ['Content-Location' => 'https://api.tugboatqa.com/v3/previews/abc123'],
        Json::encode(['preview' => 'abc123', 'job' => 'ac123']),
      ));
    }

    $matches = [];
    if (preg_match('#https://api\.tugboatqa\.com/v3/jobs/([^/]+)(/log)?$#', $uri, $matches) === 1) {
      return $this->tugboatJob($request, $matches[1], isset($matches[2]));
    }

    throw new \InvalidArgumentException("No response mocked for '{$request->getUri()}'");
  }

  /**
   * Serves a Tugboat job, keyed off the job ID so tests can pick a state.
   */
  private function tugboatJob(RequestInterface $request, string $job_id, bool $is_log): FulfilledPromise|RejectedPromise {
    if ($job_id === 'missing-job') {
      return new RejectedPromise(new ClientException(
        'Not found',
        $request,
        new Response(404, [], Json::encode(['message' => 'Not found'])),
      ));
    }
    if ($job_id === 'forbidden-job') {
      return new RejectedPromise(new ClientException(
        'Forbidden',
        $request,
        new Response(403, [], Json::encode(['message' => 'Forbidden'])),
      ));
    }

    if ($is_log) {
      return new FulfilledPromise(new Response(200, [], Json::encode([
        ['message' => 'Cloning repository'],
        // These three are noise the controller is expected to strip.
        ['message' => ' * [new branch] main -> origin/main'],
        ['message' => ' * [new tag] 1.0.0'],
        ['message' => 'branch new (next fetch will store in remotes/origin)'],
        ['message' => 'SIMPLYEST_STAGE_DOWNLOAD'],
        ['message' => 'SIMPLYEST_STAGE_PATCHING'],
        ['message' => 'Preview (simplytest) is ready'],
      ])));
    }

    $job = [
      'createdAt' => '2024-01-01T00:00:00+00:00',
      'updatedAt' => '2024-01-01T00:05:00+00:00',
      'url' => 'https://preview.tugboatqa.com/abc123',
    ];
    return new FulfilledPromise(new Response(200, [], Json::encode(match ($job_id) {
      'suspended-job' => ['type' => 'preview', 'state' => 'ready', 'suspended' => 'suspended'] + $job,
      'running-job' => ['type' => 'job', 'action' => 'building'] + $job,
      'bogus-job' => ['type' => 'not-a-real-type'] + $job,
      default => ['type' => 'preview', 'state' => 'ready'] + $job,
    })));
  }

}
