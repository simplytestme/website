<?php

namespace Drupal\simplytest_projects\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected $simplytestProjectFetcher;

  /**
   * Constructs a new ViewEditForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack object.
   * @param \Drupal\simplytest_projects\SimplytestProjectFetcher $simplytestProjectFetcher
   *   The simplytest project fetcher object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $requestStack, SimplytestProjectFetcher $simplytest_project_fetcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $requestStack;
    $this->simplytestProjectFetcher = $simplytest_project_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('simplytest_projects.fetcher')
    );
  }

  /**
   * It fulfills autocomplete request of a project.
   */
  public function autocompleteProjects() {
    $matches = [];
    if ($string = $this->requestStack->getCurrentRequest()->query->get('string')) {
      if (!$matches = $this->simplytestProjectFetcher->searchFromProjects($string)) {
        if ($project = $this->simplytestProjectFetcher->fetchProject($string)) {
          unset($project['creator']);
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
    $versions = $this->simplytestProjectFetcher->fetchProjectVersions($project);
    return new JsonResponse($versions);
  }

}
