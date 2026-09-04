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
use GuzzleHttp\Exception\GuzzleException;
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
    $lock_key = "fetch_project_$sanitized_shortname";
    if (!$this->lock->acquire($lock_key)) {
      // Could not acquire lock, another process is already fetching this project.
      // @todo Use `wait` and check if it exists. This seems like something
      //   the caller should implement?
      return NULL;
    }
    // Whatever happens past this point - a transport error, a malformed
    // payload, an entity save failure - the lock has to be released, or every
    // retry within the lock timeout is refused for no reason.
    try {
      return $this->doFetchProject($shortname);
    }
    finally {
      $this->lock->release($lock_key);
    }
  }

  /**
   * Fetches and stores the project while fetchProject() holds the lock.
   *
   * @return array{title: string, shortname: string, sandbox: bool, type: string, creator: string|null, usage: int}|null
   *   The saved project data, or NULL if the project could not be fetched.
   */
  private function doFetchProject(string $shortname): ?array {
    // Ensure the shortname is always lowercase. The Drupal.org API is not
    // case-sensitive, but other APIs are.
    $shortname = strtolower($shortname);
    $cid = 'project_fetch:' . $shortname;
    if ($cache = $this->cache->get($cid)) {
      $result = $cache->data;
    } else {
      try {
        $response = $this->httpClient->get(DrupalUrls::ORG_API . 'node.json?field_project_machine_name=' . urlencode($shortname));
      }
      catch (GuzzleException $exception) {
        $this->logger->warning('Failed to fetch initial data for %project: %message', [
          '%project' => $shortname,
          '%message' => $exception->getMessage(),
        ]);
        return NULL;
      }
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
      return NULL;
    }

    // Did we find the project we searched for?
    if (count($data['list']) === 0 || !isset($data['list'][0])) {
      return NULL;
    }
    $project_data = $data['list'][0];

    // Determine the type of this project.
    $project_type = strtolower(trim((string) $project_data['field_project_type']));
    $sandbox = $project_type !== 'full';

    // Determine project title.
    if (!isset($project_data['title'])) {
      $this->logger->warning('Failed to get initial data for %project (no project title).', [
        '%project' => $shortname,
      ]);
      return NULL;
    }
    $title = $project_data['title'];

    // Determine the project type term.
    if (!isset($project_data['type'])) {
      $this->logger->warning('Failed to get initial data for %project (no project type).', [
        '%project' => $shortname,
      ]);
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
      return NULL;
    }

    // Get author name from project url.
    if ($sandbox) {
      if (!isset($project_data['url'])) {
        $this->logger->warning('Failed to scrap user name for %project, the project node has no URL.', [
          '%project' => $shortname,
        ]);
        return NULL;
      }
      $url_parts = explode('/', (string) $project_data['url']);
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
    catch (EntityValidationException | EntityStorageException $e) {
      // Entity storage wraps whatever preSave() threw, so unpack it to tell
      // a duplicate apart from a real storage failure.
      $validation = $e instanceof EntityValidationException ? $e : $e->getPrevious();
      if ($validation instanceof EntityValidationException) {
        // A concurrent request imported the project first. It exists, which
        // is all the caller needs; the stored data is as fresh as ours.
        $this->logger->notice('Skipped saving %project: it was imported concurrently.', [
          '%project' => $shortname,
        ]);
        return $data;
      }
      // The project did not persist: callers must not report success, or the
      // client believes in a project the autocomplete will never find.
      $this->logger->error('Failed to save project %project: %message', [
        '%project' => $shortname,
        '%message' => $e->getMessage(),
      ]);
      return NULL;
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

    if ($project_ids === []) {
      return FALSE;
    }

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
   * @param string $string
   *  The prefix string to search projects for.
   * @param int $range
   *  Maximum number of results to return.
   * @param list<string>|null $types
   *  An array of project types to filter for.
   *
   * @return list<array{title: string, shortname: string, type: string}>
   *  The matching projects, best match first.
   */
  public function searchFromProjects(string $string, int $range = 100, ?array $types = NULL): array {
    $needle = strtolower(trim($string));
    // Typed the way a title reads ("Link attributes") but compared against
    // the shortname (link_attributes).
    $shortname = str_replace([' ', '-'], '_', $needle);

    $query = $this->connection->select('simplytest_project', 'p')
      ->fields('p', [
        'title',
        'shortname',
        'type',
        'sandbox',
      ]);
    // An exact match outranks everything, then shortnames that start with
    // the search, then the rest by popularity. Sorting by usage alone let a
    // popular partial match ("ai_provider_openai") bury the project actually
    // asked for ("openai"), or push it past the range cap entirely.
    // SUBSTR instead of LIKE so the expression needs no ESCAPE clause,
    // which MySQL and SQLite spell differently.
    $query->addExpression(
      'CASE WHEN p.shortname = :exact_shortname OR LOWER(p.title) = :exact_title THEN 0 WHEN SUBSTR(p.shortname, 1, ' . strlen($shortname) . ') = :prefix THEN 1 ELSE 2 END',
      'rank',
      [
        ':exact_shortname' => $shortname,
        ':exact_title' => $needle,
        ':prefix' => $shortname,
      ],
    );
    $query
      ->orderBy('rank', 'ASC')
      ->orderBy('usage', 'DESC')
      ->orderBy('sandbox', 'ASC')
      ->range(0, $range);

    $title_or_shortname = new Condition('OR');
    $title_or_shortname->condition('title', '%' . $this->connection->escapeLike($string) . '%', 'LIKE');
    $title_or_shortname->condition('shortname', '%' . $this->connection->escapeLike($string) . '%', 'LIKE');
    $title_or_shortname->condition('shortname', '%' . $this->connection->escapeLike($shortname) . '%', 'LIKE');
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
      unset($result->sandbox, $result->rank);
      $projects[] = (array) $result;
    }

    return $projects;
  }

}
