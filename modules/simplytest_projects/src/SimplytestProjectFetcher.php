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
    Connection $connection)
  {
    $this->httpClient = $http_client;
    $this->log = $logger_factory->get('simplytest_projects');
    $this->config = $config_factory->get('simplytest_projects.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
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
      $this->log->warning('Failed to get initial data for %project (empty search result).', [
        '%project' => $shortname,
      ]);
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

    if (!$force && $project->getTimestamp() > strtotime('-4 hour')) {
      return $project->getVersions();
    }

    $result = shell_exec('git ls-remote --tags ' . escapeshellarg($project->getGitWebUrl()));

    if (!empty($result) and strpos($result, 'refs/tags') === FALSE) {
      $this->log->warning('Failed to fetch version data for %project (Fetched tags).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Try to match out a list of tags of the raw HTML.
    $lines = explode("\n", $result);
    $tags = [];

    foreach ($lines as $line) {
      $tag_line = explode('refs/tags/', $line);
      if (!empty($tag_line[1])) {
        $tags[] = $tag_line[1];
      }
    }

    $result = shell_exec('git ls-remote --heads ' . escapeshellarg($project->getGitWebUrl()));

    // Try to match out a list of heads of the raw HTML.
    if (!empty($result) and strpos($result, 'refs/heads') === FALSE) {
      $this->log->warning('Failed to fetch version data for %project (Requested heads).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    $lines = explode("\n", $result);
    $heads = array();
    foreach ($lines as $line) {
      $head_line = explode('refs/heads/', $line);
      if (!empty($head_line[1])) {
        $heads[] = $head_line[1];
      }
    }

    // Blacklist filters.
    $blacklisted_versions = $this->config->get('blacklisted_versions');
    foreach ($blacklisted_versions as $blacklisted) {
      foreach ($tags as $key => $tag) {
        if (preg_match('!' . $blacklisted . '!', $tag)) {
          unset($tags[$key]);
        }
      }
      foreach ($heads as $key => $head) {
        if (preg_match('!' . $blacklisted . '!', $head)) {
          unset($heads[$key]);
        }
      }
    }

    // Now save the information about this project to database.
    $project->setVersions($tags, $heads);
    $project->set('timestamp', REQUEST_TIME);
    $project->save();

    $this->log->notice('Fetched version data for %project.', [
      '%project' => $shortname,
    ]);

    return $project->getVersions();
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
    $versions = [];
    if ($versions_data = $this->fetchVersions($project)) {
      if ($versions_data !== FALSE) {
        usort($versions_data['tags'], 'version_compare');
        $versions_data['tags'] = array_reverse($versions_data['tags']);
        foreach ($versions_data['tags'] as $tag) {
          // Find out major / api version for structure.
          if (is_numeric($tag[0])) {
            $api_version = 'Drupal ' . $tag[0];
          }
          else {
            // Prefixed with space to get it sorted to the bottom.
            $api_version = 'Other';
          }
          $versions[$api_version][$tag] = $tag;
        }
        foreach ($versions_data['heads'] as $version) {
          $versions['Branches'][$version] = $version;
        }
      }
      // Sort it in reverse: Drupal 7, Drupal 6, Branches, Other.
      krsort($versions);
    }
    return $versions;
  }

}
