<?php declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\simplytest_projects\Exception\NoReleaseHistoryFoundException;
use Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException;
use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use Drupal\simplytest_projects\ReleaseHistory\Processor;
use Drupal\simplytest_projects\ReleaseHistory\ProjectRelease;
use UnexpectedValueException;

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

  public function updateData(string $project): void {
    $invalidate_caches = FALSE;
    foreach (['current', '7.x'] as $channel) {
      try {
        $release_xml = $this->fetcher->getProjectData($project, $channel);
      }
      catch (ReleaseHistoryNotModifiedException) {
        // The release history has not been modified, so skip processing.
        continue;
      }
      try {
        $release_data = Processor::getData($release_xml);
      }
      catch (NoReleaseHistoryFoundException) {
        continue;
      }
      $invalidate_caches = TRUE;
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
            'status' => (int) ($release->status === 'published'),
            'core_compatibility' => $release->core_compatibility,
          ])
          ->execute();
      }
    }
    if ($invalidate_caches) {
      Cache::invalidateTags(["project_versions:{$project}"]);
    }
  }

  // @todo needs tests.
  public function getRelease(string $project, string $version): ?array {
    if (substr($version, -1) === 'x') {
      $version .= '-dev';
    }

    $query = $this->database->select(self::TABLE_NAME);
    $query
      ->fields(self::TABLE_NAME)
      ->condition('short_name', $project)
      ->condition('version', $version);
    return $query->execute()->fetchAssoc() ?: NULL;
  }

  // @todo needs tests.
  public function getAllReleases(string $project):array {
    $query = $this->database->select(self::TABLE_NAME);
    $query
      ->fields(self::TABLE_NAME)
      ->condition('short_name', $project)
      ->orderBy('date', 'DESC');
    return $query->execute()->fetchAll();
  }

  // @todo needs tests.
  public function getCompatibleReleases(string $project, string $core_version) {
    $releases = $this->getAllReleases($project);
    return array_values(array_filter($releases, static function (\stdClass $row) use ($core_version) {
      return Semver::satisfies($core_version, $row->core_compatibility);
    }));
  }

  public function organizeAndSortReleases(array $releases): array {
    if (count($releases) === 0) {
      return [
        'latest' => [],
        'branches' => [],
        'core' => [],
      ];
    }
    $is_core = $releases[0]->short_name === 'drupal';
    $organized_releases = [];

    $branches = [];
    $core_compatibilities = [
      [
        'label' => 'Drupal 10',
        'constraint' => '10',
        'versions' => [],
      ],
      [
        'label' => 'Drupal 9',
        'constraint' => '9',
        'versions' => [],
      ],
      [
        'label' => 'Drupal 8',
        'constraint' => '8',
        'versions' => [],
      ],
      [
        'label' => 'Drupal 7',
        'constraint' => '7',
        'versions' => [],
      ]
    ];
    foreach ($releases as $release) {
      if (strpos($release->version, '-dev') !== FALSE) {
        $branches[] = $release;
        continue;
      }

      $compatibility = $release->core_compatibility;
      foreach ($core_compatibilities as $key => $major_version) {
        try {
          if (Semver::satisfies($major_version['constraint'], $compatibility)) {
            $core_compatibilities[$key]['versions'][] = $release;
          }
        }
        catch (UnexpectedValueException) {
          // If the core compatibility is not a valid semantic version, we skip
          // it.
          continue;
        }
      }
    }

    $organized_releases['latest'] = [];
    $organized_releases['core'] = [];
    $organized_releases['branches'] = $branches;
    foreach ($core_compatibilities as $core_data) {
      if (empty($core_data['versions'])) {
        continue;
      }

      // For now, we only limit this sorting to Drupal core. This can get akward
      // with contrib which has 8.x- prefixes AND semantic versioning across
      // the same core compatibilities (see decoupled_router.)
      if ($is_core) {
        // Inspired from \Composer\Semver\Semver::usort.
        usort($core_data['versions'], static function (object $left, object $right) {
          if ($left->version === $right->version) {
            return 0;
          }
          if (Comparator::lessThan($left->version, $right->version)) {
            return 1;
          }
          return -1;
        });
      }

      $organized_releases['latest'][] = $core_data['versions'][0];
      unset($core_data['versions'][0]);
      $core_data['versions'] = array_values($core_data['versions']);
      $organized_releases['core'][] = $core_data;
    }

    // Due to the fact some versions may support multiple Drupal core majors, we
    // could have duplicate latest releases. We filter out non-unique releases
    // where.
    $latest_versions = array_map(static function (\stdClass $version) {
      return $version->version;
    }, $organized_releases['latest']);
    $latest_versions = array_unique($latest_versions);
    $organized_releases['latest'] = array_values(array_intersect_key($organized_releases['latest'], $latest_versions));


    return $organized_releases;
  }

}
