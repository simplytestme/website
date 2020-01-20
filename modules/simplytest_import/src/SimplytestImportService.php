<?php

namespace Drupal\simplytest_import;

use Drupal\Component\Serialization\Json;
use Drupal\simplytest_projects\DrupalUrls;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use GuzzleHttp\ClientInterface;

/**
 * Class SimplytestImportService.
 *
 * @package Drupal\simplytest_import
 */
class SimplytestImportService {

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Simplytest Project Fetcher.
   *
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected $projectFetcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $http_client, SimplytestProjectFetcher $simplytestProjectFetcher) {
    $this->httpClient = $http_client;
    $this->projectFetcher = $simplytestProjectFetcher;
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
  public function dataProvider($type, $page = 0) {
    $url = DrupalUrls::ORG_API . 'node.json?type=' . $type . '&page=' . $page;
    $result = $this->httpClient->get($url);
    if ($result->getStatusCode() != 200 || empty($result->getBody())) {
      $this->log->warning('Failed to fetch initial data.');
      return FALSE;
    }
    $items = Json::decode($result->getBody());
    if ($items === NULL) {
      $this->log->warning('Failed to fetch initial data.');
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
  public function getCleanData(array $items) {
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

}
