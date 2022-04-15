<?php

namespace Drupal\simplytest_projects\Plugin\QueueWorker;

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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'simplytest_projects_project_refresher' queue worker.
 *
 * @QueueWorker(
 *   id = "simplytest_projects_project_refresher",
 *   title = @Translation("Project refresher"),
 *   cron = {"time" = 60}
 * )
 */
class ProjectRefresher extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private EntityTypeManagerInterface $entityTypeManager;

  private ProjectVersionManager $projectVersionManager;

  private Client $httpClient;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ProjectVersionManager $project_version_manager,
    Client $http_client
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->projectVersionManager = $project_version_manager;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('simplytest_projects.project_version_manager'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $project = $this->entityTypeManager->getStorage('simplytest_project')->load($data);
    if ($project instanceof SimplytestProject) {
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
      catch (ServerException $exception) {
        throw new SuspendQueueException('Drupal.org API may be down.');
      }
      catch (\Exception $e) {
        // @todo do anything else?
      }

      $this->projectVersionManager->updateData($project->getShortname());
      $project->set('timestamp', \Drupal::time()->getRequestTime());
      try {
        $project->save();
      }
      catch (EntityValidationException $e) {

      }
    }
  }

}
