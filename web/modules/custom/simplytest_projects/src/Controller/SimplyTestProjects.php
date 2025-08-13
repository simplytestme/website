<?php

namespace Drupal\simplytest_projects\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
   * Constructs a new SimplyTestProjects object.
   *
   * @param \Drupal\simplytest_projects\ProjectFetcher $projectFetcher
   *   The project fetcher.
   * @param \Drupal\simplytest_projects\CoreVersionManager $coreVersionManager
   *   The core version manager.
   * @param \Drupal\simplytest_projects\ProjectVersionManager $projectVersionManager
   *   The project version manager.
   */
  public function __construct(
    private readonly ProjectFetcher $projectFetcher,
    private readonly CoreVersionManager $coreVersionManager,
    private readonly ProjectVersionManager $projectVersionManager
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_projects.core_version_manager'),
      $container->get('simplytest_projects.project_version_manager')
    );
  }

  /**
   * It fulfills autocomplete request of a project.
   */
  public function autocompleteProjects(Request $request) {
    $matches = [];
    if ($string = $request->query->get('string')) {
      if (!$matches = $this->projectFetcher->searchFromProjects($string)) {
        $string = str_replace(' ', '_', $string);
        if ($project = $this->projectFetcher->fetchProject($string)) {
          unset($project['creator'], $project['usage'], $project['sandbox']);
          $matches = [$project];
        }
      }
    }
    return new JsonResponse($matches);
  }

  /**
   * It gives the list of versions of a project.
   */
  public function projectVersions($project) {
    $versions = $this->projectVersionManager->getAllReleases($project);
    $versions = $this->projectVersionManager->organizeAndSortReleases($versions);
    $response = new CacheableJsonResponse([
      'list' => $versions
    ]);
    $response->getCacheableMetadata()->addCacheTags(["project_versions:{$project}"]);
    return $response;
  }

  public function compatibleProjectVersions($project, $core_version) {
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

}
