<?php

namespace Drupal\simplytest_projects\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\Exception\EntityValidationException;
use Drupal\simplytest_projects\ProjectVersionManager;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'simplytest_projects_project_refresher' queue worker.
 *
 * Refreshes only release data, from updates.drupal.org. The worker
 * deliberately makes no www.drupal.org API requests: refreshing the usage
 * count per project generated tens of thousands of requests a day and got
 * simplytest's IPs blocked. Usage only orders autocomplete results, and
 * relative popularity changes far too slowly to be worth that traffic.
 *
 * The `cron` key is purposely ommitted so that the queue is not processed
 * by cron. The queue should be processed on its own using the Drush command
 * for processing queues, `queue:run`.
 *
 * @QueueWorker(
 *   id = "simplytest_projects_project_refresher",
 *   title = @Translation("Project refresher"),
 * )
 */
class ProjectRefresher extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectVersionManager $projectVersionManager,
    private readonly LoggerInterface $logger,
    private readonly TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('simplytest_projects.project_version_manager'),
      $container->get('logger.channel.simplytest_projects'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function processItem($data): void {
    $project = $this->entityTypeManager->getStorage('simplytest_project')->load($data);
    if (!$project instanceof SimplytestProject) {
      $this->logger->error("Could not load project ID `$data` for project refresh.");
      return;
    }

    try {
      $this->projectVersionManager->updateData($project->getShortname());
    }
    catch (ServerException | ConnectException) {
      $this->logger->warning("Suspending project refresh queue, Drupal.org may be down.");
      throw new SuspendQueueException('Drupal.org API may be down.');
    }

    $project->set('timestamp', $this->time->getRequestTime());
    try {
      $project->save();
    }
    catch (EntityStorageException $e) {
      // Entity storage wraps whatever preSave() threw, so the validation
      // exception arrives as the previous exception rather than directly.
      $validation_exception = $e->getPrevious();
      if (!$validation_exception instanceof EntityValidationException) {
        throw $e;
      }
      $this->logger->error(sprintf(
        "Validation errors when saving project %s: %s",
        $project->label(),
        implode('|', $validation_exception->getViolationMessages())
      ));
    }
  }

}
