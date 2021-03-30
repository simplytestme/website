<?php declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Composer\Semver\Semver;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
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

  public function __construct(Connection $connection, ClientInterface $client) {
    $this->database = $connection;
    $this->client = $client;
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

}
