<?php

namespace Drupal\simplytest_launch\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Url;
use Drupal\simplytest_launch\Exception\UnprocessableHttpEntityException;
use Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition;
use Drupal\simplytest_projects\ProjectFetcher;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_tugboat\Exception\LaunchLimitExceededException;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Returns responses for config module routes.
 */
class SimplyTestLaunch implements ContainerInjectionInterface {

  private const string NOT_A_SUBMISSION = 'The request body must be a JSON object describing the launch.';

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
    }
    catch (LaunchLimitExceededException $e) {
      throw new TooManyRequestsHttpException($e->retryAfter, $e->getMessage(), $e);
    }
    catch (\Throwable $e) {
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
   * Rejects a submission that does not describe a launch we can build.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   When the body is not a JSON object.
   * @throws \Drupal\simplytest_launch\Exception\UnprocessableHttpEntityException
   *   When the submission breaks a constraint, listing every violation.
   */
  private function validateSubmission(mixed $data): void {
    if (!is_array($data)) {
      throw new BadRequestHttpException(self::NOT_A_SUBMISSION);
    }
    // Typed data builds nested properties as validation reaches them, so a
    // property of the wrong shape throws from either call.
    try {
      $constraints = $this->typedDataManager
        ->create(InstanceLaunchDefinition::create(), $data)
        ->validate();
    }
    catch (\InvalidArgumentException) {
      throw new BadRequestHttpException(self::NOT_A_SUBMISSION);
    }
    if ($constraints->count() > 0) {
      $exception = new UnprocessableHttpEntityException();
      $exception->setViolations($constraints);
      throw $exception;
    }
  }

}
