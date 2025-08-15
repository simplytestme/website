<?php

namespace Drupal\simplytest_projects;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\Exception\EntityValidationException;
use GuzzleHttp\Client;
use Drupal\Component\Serialization\Json;
use Psr\Log\LoggerInterface;

/**
 * Class SimplytestProjectFetcher
 *
 * @package Drupal\simplytest_projects
 */
class ProjectFetcher {

  public function __construct(
    private readonly Client $httpClient,
    private readonly LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
    private readonly ProjectVersionManager $projectVersionManager,
    private readonly CacheBackendInterface $cache,
    private readonly LockBackendInterface $lock,
  ) {
  }

  /**
   * Try to fetch project data from drupal.org's JSON / RESTWS API.
   *
   * @param string $shortname
   *
   * @return array|null
   *
   * @todo should not return null, but throw exceptions.
   */
  public function fetchProject(string $shortname): ?array {
    // Sanitize shortname for use in lock key: allow only lowercase letters, numbers, and underscores.
    $sanitized_shortname = preg_replace('/[^a-z0-9_]/', '_', strtolower($shortname));
    if (!$this->lock->acquire("fetch_project_$sanitized_shortname")) {
      // Could not acquire lock, another process is already fetching this project.
      // @todo Use `wait` and check if it exists. This seems like something
      //   the caller should implement?
      return NULL;
    }
    // Ensure the shortname is always lowercase. The Drupal.org API is not
    // case-sensitive, but other APIs are.
    $shortname = strtolower($shortname);
    $cid = 'project_fetch:' . $shortname;
    if ($cache = $this->cache->get($cid)) {
      $result = $cache->data;
    } else {
      $response = $this->httpClient->get(DrupalUrls::ORG_API . 'node.json?field_project_machine_name=' . urlencode($shortname));
      $result = (string) $response->getBody();
      if ($response->getStatusCode() === 200) {
        $this->cache->set($cid, $result, strtotime('+1 day'), ['project_fetch']);
      }
      else {
        $this->logger->warning('Failed to fetch initial data for %project: %data', [
          '%project' => $shortname,
          '%data' => $result,
        ]);
        return NULL;
      }
    }

    // Try to parse the received JSON.
    $data = Json::decode($result);
    if ($data === null) {
      $this->logger->warning('Failed to parse initial data for %project (json decode).', [
        '%project' => $shortname,
      ]);
      $this->lock->release("fetch_project_$sanitized_shortname");
      return NULL;
    }

    // Did we find the project we searched for?
    if (count($data['list']) === 0 || !isset($data['list'][0])) {
      $this->lock->release("fetch_project_$sanitized_shortname");
      return NULL;
    }
    $project_data = $data['list'][0];

    // Determine the type of this project.
    $project_type = strtolower(trim($project_data['field_project_type']));
    $sandbox = $project_type !== 'full';

    // Determine project title.
    if (!isset($project_data['title'])) {
      $this->logger->warning('Failed to get initial data for %project (no project title).', [
        '%project' => $shortname,
      ]);
      $this->lock->release("fetch_project_$sanitized_shortname");
      return NULL;
    }
    $title = $project_data['title'];

    // Determine the project type term.
    if (!isset($project_data['type'])) {
      $this->logger->warning('Failed to get initial data for %project (no project type).', [
        '%project' => $shortname,
      ]);
      $this->lock->release("fetch_project_$sanitized_shortname");
      return NULL;
    }
    $type_term = $project_data['type'];

    // Find out the type by term.
    $type = ProjectTypes::getProjectType($type_term);
    if ($type === FALSE) {
      // Unknown type, error.
      $this->logger->warning('Failed to get initial data for %project (Determine type for term "@term").', [
        '%project' => $shortname,
        '@term' => $type_term,
      ]);
      $this->lock->release("fetch_project_$sanitized_shortname");
      return NULL;
    }

    // Get author name from project url.
    if ($sandbox) {
      if (!isset($project_data['url'])) {
        $this->logger->warning('Failed to scrap user name from "%url".', [
          '%url' => $project_data['url'],
        ]);
        $this->lock->release("fetch_project_$sanitized_shortname");
        return NULL;
      }
      $url_parts = explode('/', $project_data['url']);
      $creator = $url_parts[4];
    }
    else {
      // Creator is irrelevant for full projects; also the username is not in the URL of them.
      $creator = NULL;
    }

    // Build an array of all the new project data.
    $data = [
      'title' => $title,
      'shortname' => $shortname,
      'sandbox' => (bool) $sandbox,
      'type' => $type,
      'creator' => $creator,
      'usage' => array_reduce(
        $project_data['project_usage'] ?? [],
        static fn (int $carry, $usage) => $carry + (int) $usage, 0
      ),
    ];

    $this->logger->notice('Fetch initial data for %project.', [
      '%project' => $shortname,
    ]);

    // Now save the information about this project to database.
    try {
      $project = SimplytestProject::create($data);
      $project->save();
    }
    catch (EntityValidationException $e) {
      // @todo decide how to handle this error if we got a dupe save.
    }
    catch (EntityStorageException $e) {
      // @todo decide how to handle this error if we got a dupe save, somehow.
    }
    finally {
      $this->lock->release("fetch_project_$sanitized_shortname");
    }
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
      ->accessCheck(FALSE)
      ->condition('shortname', $shortname)
      ->execute();

    $project = SimplytestProject::load(reset($project_ids));

    if (!$project) {
      return FALSE;
    }

    if ($project->getTimestamp() > strtotime('-6 hour')) {
      return $this->projectVersionManager->getAllReleases($shortname);
    }

    $this->projectVersionManager->updateData($shortname);
    $project->set('timestamp', \Drupal::time()->getRequestTime());
    $project->save();

    $this->logger->notice('Fetched version data for %project.', [
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
      ->orderBy('usage', 'DESC')
      ->orderBy('sandbox', 'ASC')
      ->range(0, $range);

    $title_or_shortname = new Condition('OR');
    $title_or_shortname->condition('title', '%' . $this->connection->escapeLike($string) . '%', 'LIKE');
    $title_or_shortname->condition('shortname', '%' . $this->connection->escapeLike($string) . '%', 'LIKE');
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
      unset($result->sandbox);
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
