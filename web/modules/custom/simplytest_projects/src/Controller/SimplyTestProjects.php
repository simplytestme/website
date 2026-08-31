<?php

namespace Drupal\simplytest_projects\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects\ProjectFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for config module routes.
 */
class SimplyTestProjects implements ContainerInjectionInterface {

  /**
   * How many explicit Drupal.org lookups one client may make per window.
   */
  private const int LOOKUP_FLOOD_LIMIT = 20;

  /**
   * The lookup flood window, in seconds.
   */
  private const int LOOKUP_FLOOD_WINDOW = 3600;

  /**
   * Constructs a new SimplyTestProjects object.
   *
   * @param \Drupal\simplytest_projects\ProjectFetcher $projectFetcher
   *   The project fetcher.
   * @param \Drupal\simplytest_projects\CoreVersionManager $coreVersionManager
   *   The core version manager.
   * @param \Drupal\simplytest_projects\ProjectVersionManager $projectVersionManager
   *   The project version manager.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   */
  public function __construct(
    private readonly ProjectFetcher $projectFetcher,
    private readonly CoreVersionManager $coreVersionManager,
    private readonly ProjectVersionManager $projectVersionManager,
    private readonly FloodInterface $flood
  ) {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_projects.core_version_manager'),
      $container->get('simplytest_projects.project_version_manager'),
      $container->get('flood')
    );
  }

  /**
   * It fulfills autocomplete request of a project.
   *
   * Searches known projects only. Unknown strings return an empty list and
   * the client offers an explicit lookup instead: falling through to a
   * Drupal.org API request for every unmatched keystroke generated abusive
   * request volume.
   */
  public function autocompleteProjects(Request $request) {
    $matches = [];
    if ($string = $request->query->get('string')) {
      $matches = $this->projectFetcher->searchFromProjects($string);
    }
    return new JsonResponse($matches);
  }

  /**
   * Imports one project from Drupal.org by name, on explicit user request.
   */
  public function lookupProject(Request $request): JsonResponse {
    if (!$this->flood->isAllowed('simplytest_projects.lookup', self::LOOKUP_FLOOD_LIMIT, self::LOOKUP_FLOOD_WINDOW)) {
      return new JsonResponse([
        'message' => 'Too many lookups. Try again later.',
      ], 429);
    }

    $body = json_decode($request->getContent(), TRUE);
    $name = (string) (is_array($body) ? ($body['name'] ?? '') : '');
    $shortname = strtolower(str_replace([' ', '-'], '_', trim($name)));
    if ($shortname === '' || preg_match('/^[a-z0-9_]+$/', $shortname) !== 1) {
      return new JsonResponse([
        'message' => 'Not a valid project name.',
      ], 400);
    }

    $this->flood->register('simplytest_projects.lookup', self::LOOKUP_FLOOD_WINDOW);
    $project = $this->projectFetcher->fetchProject($shortname);
    if ($project === NULL) {
      return new JsonResponse([
        'message' => "No project named $shortname was found on Drupal.org.",
      ], 404);
    }
    unset($project['creator'], $project['usage'], $project['sandbox']);
    return new JsonResponse($project);
  }

  /**
   * It gives the list of versions of a project.
   */
  public function projectVersions($project) {
    $this->refreshStaleReleases($project);
    $versions = $this->projectVersionManager->getAllReleases($project);
    $versions = $this->projectVersionManager->organizeAndSortReleases($versions);
    $response = new CacheableJsonResponse([
      'list' => $versions
    ]);
    $response->getCacheableMetadata()->addCacheTags(["project_versions:{$project}"]);
    return $response;
  }

  public function compatibleProjectVersions($project, $core_version) {
    $this->refreshStaleReleases($project);
    $versions = $this->projectVersionManager->getCompatibleReleases($project, $core_version);
    $versions = $this->projectVersionManager->organizeAndSortReleases($versions);
    $response = new CacheableJsonResponse([
      'list' => $versions
    ]);
    $response->getCacheableMetadata()->addCacheTags([
      "project_versions:{$project}",
      'core_versions'
    ]);
    return $response;
  }

  public function coreVersions(string $major_version) {
    $results = $this->coreVersionManager->getVersions((int) $major_version);
    $response = new CacheableJsonResponse([
      'list' => $results
    ]);
    $response->getCacheableMetadata()->addCacheTags(['core_versions', "core_versions:$major_version"]);
    return $response;
  }

  public function compatibleCoreVersions(string $project, string $version) {
    $release = $this->projectVersionManager->getRelease($project, $version);
    if ($release === NULL) {
      return new JsonResponse(['notfound'], 404);
    }
    $results = $this->coreVersionManager->getWithCompatibility($release['core_compatibility']);
    $response = new CacheableJsonResponse([
      'list' => $results
    ]);
    $cid = implode(':', ['core_compatibility', $project, $version]);
    // @todo loop over results to find core version tabs to attach here.
    $cache_tags = ['core_versions', $cid];
    $response->getCacheableMetadata()->addCacheTags($cache_tags);
    return $response;
  }

  /**
   * Refreshes a project's release data when it has gone stale.
   *
   * This is the primary freshness mechanism: release data updates when
   * someone actually asks for a project's versions, instead of a bulk
   * background crawl. fetchVersions() no-ops for unknown projects and for
   * projects refreshed within the last six hours, and its fetches are
   * conditional requests against updates.drupal.org.
   */
  private function refreshStaleReleases(string $project): void {
    $this->projectFetcher->fetchVersions($project, TRUE);
  }

}
