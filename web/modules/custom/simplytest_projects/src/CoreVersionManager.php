<?php declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Composer\Semver\Semver;
use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException;
use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use Drupal\update\UpdateFetcher;
use GuzzleHttp\ClientInterface;

final class CoreVersionManager {

  public const TABLE_NAME = 'simplytest_core_versions';

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  private $state;

  public function __construct(Connection $connection, ClientInterface $client, StateInterface $state) {
    $this->database = $connection;
    $this->client = $client;
    $this->state = $state;
  }

  /**
   * Gets versions for a major version of Drupal core.
   *
   * @param int $major_version
   *   The major version (7, 8, 9, etc.)
   *
   * @return object[]
   *   The versions.
   */
  public function getVersions(int $major_version): array {
    $query = $this->database->select(self::TABLE_NAME);
    $query
      ->fields(self::TABLE_NAME)
      ->condition('major', $major_version)
      ->orderBy('major', 'DESC')
      ->orderBy('minor', 'DESC')
      ->orderBy('patch', 'DESC');
    return $query->execute()->fetchAll();
  }

  /**
   * Get releases based on compatibility.
   *
   * @param string $constraint
   *   The version constraint.
   *
   * @return array
   *   The releases.
   */
  public function getWithCompatibility(string $constraint): array {
    $query = $this->database->select(self::TABLE_NAME);
    $query
      ->fields(self::TABLE_NAME)
      ->orderBy('major', 'DESC')
      ->orderBy('minor', 'DESC')
      ->orderBy('patch', 'DESC');
    $versions = $query->execute()->fetchAll();
    return array_values(array_filter($versions, static function (\stdClass $row) use ($constraint) {
      return Semver::satisfies($row->version, $constraint);
    }));
  }

  /**
   * Updates stored Drupal core release data for a major version.
   *
   * @param int $major_version
   *   The major version (7, 8, 9, etc.)
   */
  public function updateData(int $major_version): void {
    if ($major_version < 7) {
      throw new \InvalidArgumentException("The major version '$major_version' is not supported");
    }
    // @todo this is a bit of a workaround until this is fully refactored.
    // See method for details.
    try {
      $this->preflightShouldUpdateCheck($major_version);
    }
    catch (ReleaseHistoryNotModifiedException $e) {
      return;
    }

    $releases = [];
    $current_page = 0;
    $has_next_page = TRUE;

    $query = [
      'type' => 'project_release',
      'field_release_project' => 3060,
      'limit' => '100',
      'sort' => 'created',
      'direction' => 'desc',
      'page' => $current_page,
      'field_release_version_major' => $major_version,
    ];

    while ($has_next_page) {
      $query['page'] = $current_page;
      $url = "https://www.drupal.org/api-d7/node.json?" . http_build_query($query);
      $response = $this->client->get($url);
      $data = Json::decode((string) $response->getBody());
      $releases[] = array_map(static function (array $node) {
        $release_types = array_column($node['taxonomy_vocabulary_7'], 'id');
        return [
          'version' => $node['field_release_version'],
          'major' => $node['field_release_version_major'],
          'minor' => $node['field_release_version_minor'] ?? '0',
          'patch' => $node['field_release_version_patch'],
          'extra' => $node['field_release_version_extra'],
          'vcs_label' => $node['field_release_vcs_label'],
          'insecure' => (int) in_array('188131', $release_types, TRUE),
        ];
      }, $data['list']);
      $current_page++;
      $has_next_page = isset($data['next']);
    }

    $releases = array_merge(...$releases);
    foreach ($releases as $release) {
      $this->database->merge(self::TABLE_NAME)
        ->keys(['version' => $release['version']])
        ->fields($release)
        ->execute();
    }
    Cache::invalidateTags(["core_versions:$major_version"]);
  }

  /**
   * Checks if we should process the update check.
   *
   * The Drupal.org JSON API endpoints do not leverage the Last-Modified header
   * like the Updates XML API. However, this service was written before we began
   * using that Updates XML API. So, to prevent a complete rewrite of this
   * service upfront, this method is a compromise. It checks if the update XML
   * has changed and uses that to determine if we should fetch verbose release
   * data over the Drupal.org JSON API.
   *
   * Changes here have side effects to the core versions returned to the
   * frontend, given the hesitancy to deal with the change right now.
   *
   * @param int $major_version
   *   The major version (7, 8, 9, etc.)
   *
   * @see \Drupal\simplytest_projects\ReleaseHistory\Fetcher::getProjectData
   * @todo Decide if we still want this service or just handle it in ProjectVersionManager alone.
   *
   * @throws \Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException
   */
  private function preflightShouldUpdateCheck(int $major_version): void {
    $channel = $major_version === 7 ? '7.x' : 'current';
    $headers = [];
    // When sending an If-Modified-Since header, the server will return a 200
    // if the content has been modified, otherwise it returns 304.
    // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-Modified-Since
    $last_modified = $this->state->get("release_history_last_modified:drupal:$major_version");
    if ($last_modified) {
      $headers['If-Modified-Since'] = gmdate(DateTimePlus::RFC7231, $last_modified);
    }
    // We perform a HEAD request here, since we don't care about the response.
    // This is a hacky preflight check to see if we should do a full API pull.
    $response = $this->client->head(UpdateFetcher::UPDATE_DEFAULT_URL . '/drupal/' . $channel, ['headers' => $headers]);
    if ($response->getStatusCode() === 304) {
      throw new ReleaseHistoryNotModifiedException();
    }
    $this->state->set("release_history_last_modified:drupal:$major_version", strtotime($response->getHeaderLine('Last-Modified')));
  }

}
