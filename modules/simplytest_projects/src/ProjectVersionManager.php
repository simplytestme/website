<?php declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Composer\Semver\Semver;
use Drupal\Core\Database\Connection;
use Drupal\simplytest_projects\Exception\NoReleaseHistoryFoundException;
use Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException;
use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use Drupal\simplytest_projects\ReleaseHistory\Processor;
use Drupal\simplytest_projects\ReleaseHistory\ProjectRelease;

final class ProjectVersionManager {

  public const TABLE_NAME = 'simplytest_project_versions';

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * @var \Drupal\simplytest_projects\ReleaseHistory\Fetcher
   */
  private $fetcher;

  public function __construct(Connection $connection, Fetcher $fetcher) {
    $this->database = $connection;
    $this->fetcher = $fetcher;
  }

  // @todo needs tests
  public function updateData(string $project) {
    foreach (['current', '7.x'] as $channel) {
      try {
        $release_xml = $this->fetcher->getProjectData($project, $channel);
      }
      catch (ReleaseHistoryNotModifiedException $e) {
        // The release history has not been modified, so skip processing.
        continue;
      }
      try {
        $release_data = Processor::getData($release_xml);
      }
      catch (NoReleaseHistoryFoundException $e) {
        continue;
      }
      foreach ($release_data['releases'] as $release) {
        assert($release instanceof ProjectRelease);
        $this->database->merge(self::TABLE_NAME)
          ->keys([
            'short_name' => $project,
            'version' => $release->version
          ])
          ->fields([
            'short_name' => $project,
            'version' => $release->version,
            'tag' => $release->tag,
            'date' => $release->date,
            'status' => $release->status === 'published',
            'core_compatibility' => $release->core_compatibility,
          ])
          ->execute();
      }
    }
  }

  // @todo needs tests.
  public function getRelease(string $project, string $version): ?array {
    $query = $this->database->select(self::TABLE_NAME);
    $query
      ->fields(self::TABLE_NAME)
      ->condition('short_name', $project)
      ->condition('version', $version);
    return $query->execute()->fetchAssoc();
  }

  // @todo needs tests.
  public function getAllReleases(string $project):array {
    $query = $this->database->select(self::TABLE_NAME);
    $query
      ->fields(self::TABLE_NAME)
      ->condition('short_name', $project);
    return $query->execute()->fetchAll();
  }

  // @todo needs tests.
  public function getCompatibleReleases(string $project, string $core_version) {
    $releases = $this->getAllReleases($project);
    return array_values(array_filter($releases, static function (\stdClass $row) use ($core_version) {
      return Semver::satisfies($core_version, $row->core_compatibility);
    }));
  }

}
