<?php

namespace Drupal\simplytest_projects\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\simplytest_projects\DrupalUrls;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\Exception\EntityValidationException;
use Drupal\simplytest_projects\ProjectVersionManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'simplytest_projects_project_refresher' queue worker.
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
    private readonly Client $httpClient,
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
      $container->get('http_client'),
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
    // @todo project fetcher does this, but also saves the project.
    //    eventually reduce this duplication
    try {
      $result = $this->httpClient->get(DrupalUrls::ORG_API . 'node.json?field_project_machine_name=' . urlencode($project->getShortname()));
      $data = Json::decode($result->getBody());
      $project_data = $data['list'][0];

      $project->set('usage', array_reduce(
        $project_data['project_usage'] ?? [0],
        static fn (int $carry, $usage) => $carry + (int) $usage, 0
      ));
    }
    catch (ServerException) {
      $this->logger->warning("Suspending project refresh queue, Drupal.org may be down.");
      throw new SuspendQueueException('Drupal.org API may be down.');
    }
    catch (\Exception) {
      // @todo do anything else?
    }

    $this->projectVersionManager->updateData($project->getShortname());

    $project->set('timestamp', $this->time->getRequestTime());
    try {
      $project->save();
    }
    catch (EntityValidationException $e) {
      $this->logger->error("Validation errors when saving project {$project->label()}: {$e->getMessage()}");
      $this->logger->error(sprintf(
        "Validation errors when saving project %s: %s",
        $project->label(),
        implode('|', $e->getViolationMessages())
      ));
    }
  }

}
