<?php

namespace Drupal\simplytest_launch\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for config module routes.
 */
class SimplyTestLaunch extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Simplytest Project Fetcher Service.
   *
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected $simplytestProjectFetcher;

  /**
   * Simplytest Project Fetcher Service.
   *
   * @var \Drupal\simplytest_tugboat\InstanceManagerInterface
   */
  protected $instanceManager;

  /**
   * Constructs a new ViewEditForm object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack object.
   * @param \Drupal\simplytest_projects\SimplytestProjectFetcher
   *   The simplytest fetcher service.
   * @param \Drupal\simplytest_tugboat\InstanceManagerInterface
   *   The simplytest tugboat instance manager service.
   */
  public function __construct(RequestStack $requestStack, SimplytestProjectFetcher $simplytest_project_fetcher, InstanceManagerInterface $instance_manager) {
    $this->requestStack = $requestStack;
    $this->simplytestProjectFetcher = $simplytest_project_fetcher;
    $this->instanceManager = $instance_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_tugboat.instance_manager')
    );
  }

  /**
   * Return response for the controller.
   */
  public function projectSelector() {
    return [];
  }

  /**
   * Project launcher service for react.
   */
  public function launchProject() {
    $submission = Json::decode($this->requestStack->getCurrentRequest()->getContent());

    // @todo add more validation when ocd button is clicked.
    if ($error = $this->validateSubmission($submission)) {
      return new JsonResponse(['error' => $error]);
    }

    // @todo launch submission for react.
    // $this->instanceManager->launchInstance($submission);

    return new JsonResponse($submission);
  }

  /**
   * Helper method to validate the submitted data.
   */
  private function validateSubmission($data) {
    $project = $data['project'];
    $project = strtolower(trim($project));
    $version = $data['version'];
    $ocd_id = (isset($data['ocd_id'])) ? $data['ocd_id'] : NULL;

    // @todo Flood protection check.

    // @todo add more validation for ocd_id.
    if ($ocd_id) {
      return NULL;
    }

    // Check whether a project shortname was submitted.
    if (empty($project)) {
      return 'Please enter a project shortname to launch a sandbox for.';
    }

    // Before we try to fetch anything, check whether this shortname is valid.
    if (preg_match('/[^a-z_\-0-9]/i', $project)) {
      return 'Please enter a valid shortname of a module, theme or distribution.';
    }

    // Get available project versions.
    $versions = $this->simplytestProjectFetcher->fetchVersions($project);

    // Check whether the submitted project exists.
    if ($versions === FALSE) {
      return t('The selected project shortname %project could not be found.',
        ['%project' => $project]
      );
    }

    // Check whether the selected has any available releases.
    elseif (empty($versions['heads']) && empty($versions['tags'])) {
      return t('The selected project %project has no available releases. (Release cache is cleared once an hour)',
        array('%project' => $project)
      );
    }
    // Check if there was even a version selected for the project.
    elseif (empty($version)) {
      return t('No version was selected for the requested project.',
        array('%project' => $project)
      );
    }
    // Check whether the selected version is a known tag or branch.
    elseif (!in_array($version, $versions['tags']) && !in_array($version, $versions['heads'])) {
      // Even if the selected version is no known tag or branch it's still
      // possible that it's not a version but a specific commit.
      return t('There is no release available with the selected version %version.',
        array('%version' => $version)
      );
    }
  }

}
