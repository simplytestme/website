<?php

namespace Drupal\simplytest_launch\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\simplytest_launch\Exception\UnprocessableHttpEntityException;
use Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Returns responses for config module routes.
 */
class SimplyTestLaunch implements ContainerInjectionInterface {

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
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typeDataManager;

  /**
   * Constructs a new ViewEditForm object.
   *
   * @param \Drupal\simplytest_projects\SimplytestProjectFetcher
   *   The simplytest fetcher service.
   * @param \Drupal\simplytest_tugboat\InstanceManagerInterface
   *   The simplytest tugboat instance manager service.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(SimplytestProjectFetcher $simplytest_project_fetcher, InstanceManagerInterface $instance_manager, TypedDataManagerInterface $typed_data_manager) {
    $this->simplytestProjectFetcher = $simplytest_project_fetcher;
    $this->instanceManager = $instance_manager;
    $this->typeDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_tugboat.instance_manager'),
      $container->get('typed_data_manager')
    );
  }

  public function configure(Request $request) { 
    $build = [
      'mount' => [
        '#markup' => Markup::create('<div class="simplytest-react-component" id="launcher_mount"></div>'),
        '#attached' => [
          'library' => [
            'simplytest_theme/launcher',
          ],
          'drupalSettings' => [
            // Pass custom launcher values to drupalSettings.
            'launcher' => $request->query->get('launcher', []),
          ],
        ],
      ],
    ];
    return $build;
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
  public function launchProject(Request $request) {
    $content = $request->getContent();
    $submission = Json::decode($content);
    $this->validateSubmission($submission);

    try {
       $instance = $this->instanceManager->launchInstance($submission);
    } catch (\Throwable $e) {
      throw new ServiceUnavailableHttpException(null, $e->getMessage(), $e);
    }
    return new JsonResponse(
      // @todo return data about the instance.
      [
        'status' => 'OK',
        'progress' => Url::fromRoute('simplytest_tugboat.progress', [
          'instance_id' => $instance['tugboat']['preview_id'],
          'job_id' => $instance['tugboat']['job_id'],
        ])->setAbsolute()->toString()
      ] + $instance,
    );
  }

  /**
   * Helper method to validate the submitted data.
   *
   * @todo \Drupal\Core\EventSubscriber\ExceptionJsonSubscriber double encodes
   *    the errors thrown here. just return the constraints and manually return
   *    a JSON response of 422.
   */
  private function validateSubmission($data) {
    // @todo Flood protection preflight check (IP based, possibly a problem.)
    try {
      $definition = $this->typeDataManager->create(InstanceLaunchDefinition::create(), $data);
    }
    catch (\Throwable $e) {
      throw new ServiceUnavailableHttpException(null, $e->getMessage());
    }
    $constraints = $definition->validate();
    if ($constraints->count() > 0) {
      $exception = new UnprocessableHttpEntityException();
      $exception->setViolations($constraints);
      throw $exception;
    }

    // @todo convert these following checks into constraints.
    $project = $data['project']['shortname'];
    $version = $data['project']['version'];

    // Get available project versions.
    $versions = array_map(static function (\stdClass $release) {
      return $release->version;
    }, $this->simplytestProjectFetcher->fetchVersions($project));

    // Check whether the submitted project exists.
    if ($versions === FALSE) {
      throw new UnprocessableEntityHttpException(Json::encode([
        'errors' => [new TranslatableMarkup(
          'The selected project shortname %project could not be found.',
          ['%project' => $project]
        )]
      ]));
    }

    // Check whether the selected version is a known tag or branch.
    // @todo this should be in the data type constraint.
    if (!in_array($version, $versions, TRUE)) {
      // Even if the selected version is no known tag or branch it's still
      // possible that it's not a version but a specific commit.
      throw new UnprocessableEntityHttpException(Json::encode([
        'errors' => [new TranslatableMarkup(
          'There is no release available with the selected version %version.',
          ['%version' => $version]
        )]
      ]));
    }
  }

}
