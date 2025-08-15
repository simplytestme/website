<?php

namespace Drupal\simplytest_projects;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\simplytest_projects\DrupalUrls;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectFetcher;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Class SimplytestImportService.
 *
 * @package Drupal\simplytest_import
 */
class ProjectImporter {

  /**
   * {@inheritdoc}
   */
  public function __construct(
      /**
       * GuzzleHttp\ClientInterface definition.
       */
      protected ClientInterface $httpClient,
      /**
       * Simplytest Project Fetcher.
       */
      protected ProjectFetcher $projectFetcher,
      private readonly LoggerInterface $logger
  )
  {
  }

  /**
   * Get items list from drupal.org api.
   *
   * @param string $type
   *   Type to be fetched.
   * @param int $page
   *   Page number for the request.
   *
   * @return bool|mixed
   *   Items dataset.
   */
  public function fetchData(string $type, int $page = 0) {
    $allowed_types = ['project_module', 'project_theme', 'project_distribution',];
    if (!in_array($type, $allowed_types)) {
      throw new \InvalidArgumentException("The type '$type' is not allowed");
    }

    $url = DrupalUrls::ORG_API . 'node.json?type=' . $type . '&page=' . $page;
    $result = $this->httpClient->get($url);
    if ($result->getStatusCode() !== 200 || $result->getBody() === NULL) {
      $this->logger->warning('Failed to fetch initial data.');
      return FALSE;
    }
    $items = Json::decode($result->getBody());
    if ($items === NULL) {
      $this->logger->warning('Failed to fetch initial data.');
      return FALSE;
    }
    return $items;
  }

  /**
   * Check of item exist and structured the data to be imported.
   *
   * @param array $items
   *   Items list.
   *
   * @return array
   *   Dataset to be imported.
   */
  public function filterExistingProjects(array $items): array {
    $data = [];

    foreach ($items as $item) {
      // Look if the item already exist.
      if (empty($this->projectFetcher->searchFromProjects($item['field_project_machine_name']))) {
        $data[] = [
          'title' => $item['title'],
          'shortname' => $item['field_project_machine_name'],
          'sandbox' => $item['field_project_type'] === 'sandbox' ? 1 : 0,
          'type' => ProjectTypes::getProjectType($item['type']),
          'creator' => !empty($item['author']) ? $item['author']['name'] : '',
        ];
      }
    }
    return $data;
  }

  public function buildBatch(string $type): BatchBuilder {
    $allowed_types = ['module', 'theme', 'distribution',];
    if (!in_array($type, $allowed_types)) {
      throw new \InvalidArgumentException("The type '$type' is not allowed.");
    }
    $type = 'project_' . $type;
    $items = $this->fetchData($type);

    if (preg_match('/&page=(\d*)/', (string) $items['last'], $count)) {
      $count = $count[1];
    } else {
      $count = 0;
    }

    $batch_builder = (new BatchBuilder())
      ->setTitle(new FormattableMarkup('Importing @num pages of @type',
        [
          '@num' => $count,
          '@type' => str_replace('project_', '', $type),
        ]
      ))
      ->setFinishCallback([self::class, 'batchFinished']);
    for ($index = 0; $index < $count; $index++) {
      $batch_builder->addOperation([self::class, 'batchProcess'], [$index, $type]);
    }
    return $batch_builder;
  }

  public static function batchProcess($index, $type, &$context): void {
    $importService = \Drupal::service('simplytest_projects.importer');
    assert($importService instanceof self);
    $items = $importService->fetchData($type, $index);
    $data = $importService->filterExistingProjects($items['list']);
    foreach ($data as $datum) {
      try {
        $project = SimplytestProject::create($datum);
        $project->save();
      }
      catch (EntityStorageException) {
        // @todo decide how to handle this error if we got a dupe save, somehow.
      }
    }
    $context['results']['processed'] += count($data);
    $context['results']['type'] = $type;
  }

  public static function batchFinished($success, $results, $operations): void {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Total @count @type imported.',
        [
          '@count' => $results['processed'] + 1,
          '@type' => str_replace('project_', '', $results['type']),
        ]));
    }
    else {
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
