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

final readonly class ProjectVersionManager {

  public const string TABLE_NAME = 'simplytest_project_versions';

  public function __construct(
      /**
       * The database.
       */
      private Connection $database,
      private Fetcher $fetcher
  )
  {
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
            'version' => $release->version,
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
    if (str_ends_with($version, 'x')) {
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
    return array_values(array_filter($releases, static fn(\stdClass $row) => Semver::satisfies($core_version, $row->core_compatibility)));
  }

  public function organizeAndSortReleases(array $releases): array {
    if (count($releases) === 0) {
      return [
        'latest' => [],
        'branches' => [],
        'core' => [],
      ];
    }
    $organized_releases = [];

    $branches = [];
    $core_compatibilities = [
      [
        'label' => 'Drupal 11',
        'constraint' => '11',
        'versions' => [],
      ],
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
      ],
    ];
    foreach ($releases as $release) {
      if (str_contains($release->version, '-dev')) {
        $branches[] = $release;
        continue;
      }
      [$release_major] = explode('.', self::stripLegacyPrefix($release->version), 2);
      try {
        $compatibility = $release->core_compatibility;
        foreach ($core_compatibilities as $key => $major_version) {
            if (Semver::satisfies($major_version['constraint'], $compatibility)) {
              $core_compatibilities[$key]['versions'][$release_major][] = $release;
            }
          }
      }
      catch (UnexpectedValueException) {
        // If the core compatibility is not a valid semantic version, we skip
        // it.
        continue;
      }
    }
    self::sortVersions($branches);

    $organized_releases['latest'] = [];
    $organized_releases['core'] = [];
    $organized_releases['branches'] = $branches;
    foreach ($core_compatibilities as $core_data) {
      if (empty($core_data['versions'])) {
        continue;
      }
      krsort($core_data['versions']);

      // @todo is this sorting needed if they are in order from release history?
      foreach ($core_data['versions'] as &$core_versions) {
        self::sortVersions($core_versions);
      }
      unset($core_versions);

      foreach ($core_data['versions'] as &$core_versions) {
        $organized_releases['latest'][] = array_shift($core_versions);
      }
      unset($core_versions);
      $core_data['versions'] = array_merge(...$core_data['versions']);
      $organized_releases['core'][] = $core_data;
    }

    // Due to the fact some versions may support multiple Drupal core majors, we
    // could have duplicate latest releases. We filter out non-unique releases
    // where.
    $latest_versions = array_map(static fn(\stdClass $version) => $version->version, $organized_releases['latest']);
    $latest_versions = array_unique($latest_versions);
    $organized_releases['latest'] = array_values(array_intersect_key($organized_releases['latest'], $latest_versions));


    return $organized_releases;
  }

  private static function stripLegacyPrefix(string $release): string {
    return str_replace(['8.x-', '7.x-'], '', $release);
  }

  /**
   * @param list<object{version: string}> $versions
   */
  private static function sortVersions(array &$versions): void {
    usort($versions, static function (object $left, object $right) {
      $left_version = self::stripLegacyPrefix($left->version);
      $right_version = self::stripLegacyPrefix($right->version);
      if ($left_version === $right_version) {
        return 0;
      }
      if (Comparator::lessThan($left_version, $right_version)) {
        return 1;
      }
      return -1;
    });
  }

}
