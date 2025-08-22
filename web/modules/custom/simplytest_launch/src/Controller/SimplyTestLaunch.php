<?php

namespace Drupal\simplytest_launch\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\simplytest_launch\Exception\UnprocessableHttpEntityException;
use Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition;
use Drupal\simplytest_projects\ProjectFetcher;
use Drupal\simplytest_projects\ProjectVersionManager;
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

  public function __construct(
    private readonly ProjectFetcher $projectFetcher,
    private readonly InstanceManagerInterface $instanceManager,
    private readonly TypedDataManagerInterface $typedDataManager,
    private readonly Connection $database,
    private readonly ProjectVersionManager $projectVersionManager
  ) {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_tugboat.instance_manager'),
      $container->get('typed_data_manager'),
      $container->get('database'),
      $container->get('simplytest_projects.project_version_manager')
    );
  }

  public function configure(Request $request): array {
    return [
      'mount' => [
        '#markup' => Markup::create('<div class="simplytest-react-component" id="launcher_mount"></div>'),
        '#attached' => [
          'library' => [
            'simplytest_theme/launcher',
          ],
          'drupalSettings' => [
            // Pass custom launcher values to drupalSettings.
            'launcher' => $request->query->get('launcher'),
          ],
        ],
      ],
    ];
  }

  /**
   * Return response for the controller.
   */
  public function projectSelector(string $project, string $version, Request $request): LocalRedirectResponse {
    if (str_ends_with($version, 'x')) {
      $version .= '-dev';
    }
    $query = [
        'project' => $project,
        'version' => $version
    ] + $request->query->all();

    $count = (int) $this->database->select('simplytest_project', 'p')
      ->condition('shortname', $project)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($count === 0) {
      // @note on project insert, the release history is automatically fetched.
      // @see simplytest_projects_simplytest_project_insert
      $this->projectFetcher->fetchProject($project);
    }
    else if ($version !== '') {
      $release = $this->projectVersionManager->getRelease($project, $version);
      if ($release === NULL) {
        $this->projectVersionManager->updateData($project);
      }
    }

    $configure_url = Url::fromRoute('simplytest_launch.configure', [], [
      'query' => $query,
    ]);

    $configure_url_generated = $configure_url->toString(TRUE);
    $response = new LocalRedirectResponse($configure_url_generated->getGeneratedUrl());
    $response->addCacheableDependency($configure_url_generated);

    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->addCacheContexts(['url.query_args']);
    $cacheable_metadata->addCacheTags(["project_versions:$project"]);
    $response->addCacheableDependency($cacheable_metadata);
    return $response;
  }



  /**
   * Project launcher service for react.
   */
  public function launchProject(Request $request): JsonResponse {
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
      $definition = $this->typedDataManager->create(InstanceLaunchDefinition::create(), $data);
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
    $versions = array_map(static fn(\stdClass $release) => $release->version, $this->projectFetcher->fetchVersions($project));

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
