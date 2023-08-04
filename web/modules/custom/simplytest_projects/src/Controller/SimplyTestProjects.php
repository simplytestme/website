<?php

namespace Drupal\simplytest_projects\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects\ProjectFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for config module routes.
 */
class SimplyTestProjects extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The simplytest project fetcher object.
   *
   * @var \Drupal\simplytest_projects\ProjectFetcher
   */
  protected $projectFetcher;

  /**
   * The core version manager.
   *
   * @var \Drupal\simplytest_projects\CoreVersionManager
   */
  private $coreVersionManager;

  /**
   * The project version manager.
   *
   * @var \Drupal\simplytest_projects\ProjectVersionManager
   */
  private $projectVersionManager;

  /**
   * Constructs a new ViewEditForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack object.
   * @param \Drupal\simplytest_projects\ProjectFetcher $simplytest_project_fetcher
   *   The project fetcher.
   * @param \Drupal\simplytest_projects\CoreVersionManager $core_version_manager
   *   The core version manager.
   * @param \Drupal\simplytest_projects\ProjectVersionManager $project_version_manager
   *   The project version manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $requestStack, ProjectFetcher $simplytest_project_fetcher, CoreVersionManager $core_version_manager, ProjectVersionManager $project_version_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $requestStack;
    $this->projectFetcher = $simplytest_project_fetcher;
    $this->coreVersionManager = $core_version_manager;
    $this->projectVersionManager = $project_version_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
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
          unset($project['creator']);
          unset($project['usage']);
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
