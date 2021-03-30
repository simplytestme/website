<?php

namespace Drupal\simplytest_projects;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use GuzzleHttp\Client;
use Drupal\Component\Serialization\Json;

/**
 * Class SimplytestProjectFetcher
 *
 * @package Drupal\simplytest_projects
 */
class SimplytestProjectFetcher {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  public $log;

  /**
   * @var \GuzzleHttp\Client
   */
  public $httpClient;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  public $config;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  public $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  public $connection;

  private $projectVersionManager;

  /**
   * SimplytestProjectFetcher constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   * @param \Drupal\Core\Database\Connection $connection
   */
  public function __construct(
    Client $http_client,
    LoggerChannelFactory $logger_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection,
    ProjectVersionManager $project_version_manager
  )
  {
    $this->httpClient = $http_client;
    $this->log = $logger_factory->get('simplytest_projects');
    $this->config = $config_factory->get('simplytest_projects.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->projectVersionManager = $project_version_manager;
  }

  /**
   * Try to fetch project data from drupal.org's JSON / RESTWS API.
   *
   * @param $shortname
   *
   * @return array|bool
   * @throws \Exception
   */
  public function fetchProject($shortname) {
    $result = $this->httpClient->get(DrupalUrls::ORG_API . 'node.json?field_project_machine_name=' . urlencode($shortname));
    if ($result->getStatusCode() != 200 || empty($result->getBody())) {
      $this->log->warning('Failed to fetch initial data for %project (Request).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Try to parse the received JSON.
    $data = Json::decode($result->getBody());
    if ($data === null) {
      $this->log->warning('Failed to parse initial data for %project (json decode).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Did we find the project we searched for?
    if (count($data['list']) === 0 || !isset($data['list'][0])) {
      return FALSE;
    }
    $project_data = $data['list'][0];

    // Determine the type of this project.
    $project_type = strtolower(trim($project_data['field_project_type']));
    switch ($project_type) {

      case 'full':
        $sandbox = FALSE;
        break;

      case 'sandbox':
        $sandbox = TRUE;
        break;

      default:
        $this->log->warning('Failed to get initial data for %project (invalid project type "@type").',[
          '%project' => $shortname,
          '@type' => $project_type,
        ]);
        return FALSE;
    }

    // Determine project title.
    if (!isset($project_data['title'])) {
      $this->log->warning('Failed to get initial data for %project (no project title).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    $title = $project_data['title'];

    // Determine the project type term.
    if (!isset($project_data['type'])) {
      $this->log->warning('Failed to get initial data for %project (no project type).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    $type_term = $project_data['type'];

    // Find out the type by term.
    $type = ProjectTypes::getProjectType($type_term);
    if ($type === FALSE) {
      // Unknown type, error.
      $this->log->warning('Failed to get initial data for %project (Determine type for term "@term").', [
        '%project' => $shortname,
        '@term' => $type_term,
      ]);
      return FALSE;
    }

    // Get author name from project url.
    if ($sandbox) {
      if (!isset($project_data['url'])) {
        $this->log->warning('Failed to scrap user name from "%url".', [
          '%url' => $project_data['url'],
        ]);
        return FALSE;
      }
      $url_parts = explode('/', $project_data['url']);
      $creator = $url_parts[4];
    }
    else {
      // Creator is irrelevant for full projects; also the username is not in the URL of them.
      $creator = NULL;
    }

    // Build an array of all the new project data.
    $data = array(
      'title' => $title,
      'shortname' => $shortname,
      'sandbox' => (int) $sandbox,
      'type' => $type,
      'creator' => $creator,
    );

    $this->log->notice('Fetch initial data for %project.', [
      '%project' => $shortname,
    ]);

    // Now save the information about this project to database.
    $project = SimplytestProject::create($data);
    $project->save();

    $this->fetchVersions($shortname, TRUE);

    return $data;
  }

  /**
   * Fetches, saves and returns all available versions for a project.
   *
   * @param string $shortname
   *  The project's shortname to fetch available versions for.
   *
   * @return array|FALSE
   *  An associative array containing:
   *   - tags: Existing tags of the project.
   *   - heads: Existing heads of the project.
   */
  public function fetchVersions($shortname, $force = FALSE) {
    if (!$force) {
      return $this->projectVersionManager->getAllReleases($shortname);
    }

    // Check whether project is known in database.
    $project_ids = $this->entityTypeManager
      ->getStorage('simplytest_project')
      ->getQuery('AND')
      ->condition('shortname', $shortname)
      ->execute();

    $project = SimplytestProject::load(reset($project_ids));

    if (!$project) {
      return FALSE;
    }

    if ($project->getTimestamp() > strtotime('-4 hour')) {
      return $this->projectVersionManager->getAllReleases($shortname);
    }

    $this->projectVersionManager->updateData($shortname);
    $project->set('timestamp', \Drupal::time()->getRequestTime());
    $project->save();

    $this->log->notice('Fetched version data for %project.', [
      '%project' => $shortname,
    ]);

    return $this->projectVersionManager->getAllReleases($shortname);
  }

  /**
   * Searches from the list of existing projects.
   *
   * @param $string
   *  The prefix string to search projects for.
   * @param int $range
   *  Maximum number of results to return.
   * @param array $types
   *  An array of project types to filter for.
   *
   * @return array
   *  An array of standard objects containing:
   *   - title: The human readable project title.
   *   - type: The projects type.
   *   - shortname: The project machine/shortname.
   *   - sandbox: Whether it's a sandbox.
   */
  public function searchFromProjects($string, $range = 100, $types = NULL) {
    $query = $this->connection->select('simplytest_project', 'p')
      ->fields('p', [
        'title',
        'shortname',
        'type',
        'sandbox',
      ])
      ->orderBy('sandbox', 'ASC')
      ->range(0, $range);

    $title_or_shortname = new Condition('OR');
    $title_or_shortname->condition('title', $this->connection->escapeLike($string) . '%', 'LIKE');
    $title_or_shortname->condition('shortname', $this->connection->escapeLike($string) . '%', 'LIKE');
    $query->condition($title_or_shortname);

    if ($types) {
      $types_or = new Condition('OR');
      foreach ($types as $type) {
        $types_or->condition('type', $type);
      }
      $query->condition($types_or);
    }

    $results = $query->execute()->fetchAll();

    $projects = [];
    foreach ($results as $result) {
      $projects[] = (array) $result;
    }

    return $projects;
  }

  /**
   * Fetch all the versions of a project.
   *
   * @param $project
   * @return array
   */
  public function fetchProjectVersions($project) {
    $versions = [
      'branches' => [],
      'tags' => [],
    ];
    if ($versions_data = $this->fetchVersions($project)) {
      if ($versions_data !== FALSE) {
        if (isset($versions_data['tags'])) {
          $version_groups = [];
          usort($versions_data['tags'], 'version_compare');
          foreach ($versions_data['tags'] as $tag) {
            // Support for legacy versioning and semantic versioning.
            if (strpos($tag, '.x-') === 1) {
              $api_version = 'Drupal ' . $tag[0];
            }
            // Find out major / api version for structure.
            elseif (is_numeric($tag[0])) {
              $api_version = $tag[0] . '.x';
            }
            else {
              // Prefixed with space to get it sorted to the bottom.
              $api_version = 'Other';
            }
            $version_groups[$api_version][] = $tag;
          }
          foreach ($version_groups as $api_version => $tags) {
            // This is kind of messy and duplicative, but it works for now.
            $drupal_major_version = '';
            $first_tag = $tags[0];
            if (strpos($first_tag, '.x-') === 1) {
              $drupal_major_version = $first_tag[0];
            } else {
              // Assume all semver is D9.
              $drupal_major_version = '9';
            }
            $versions['tags'][] = [
              'grouping' => $api_version,
              'major' => $drupal_major_version,
              'tags' => array_reverse($tags)
            ];
          }
        }
        if (isset($versions_data['heads'])) {
          foreach ($versions_data['heads'] as $version) {
            if (strpos($version, '.x-') === 1) {
              $drupal_major_version = $version[0];
            } else {
              // Assume all semver is D9.
              $drupal_major_version = '9';
            }
            $versions['branches'][] = [
              'major' => $drupal_major_version,
              'branch' => $version,
            ];
          }
        }
      }
    }
    return $versions;
  }

}
