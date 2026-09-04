<?php declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Composer\Semver\Semver;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\simplytest_projects\Exception\NoReleaseHistoryFoundException;
use Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException;
use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use Drupal\simplytest_projects\ReleaseHistory\Processor;
use Drupal\simplytest_projects\ReleaseHistory\ProjectRelease;

final readonly class CoreVersionManager {

  public const string TABLE_NAME = 'simplytest_core_versions';

  /**
   * Distinguishes this consumer's If-Modified-Since state from
   * ProjectVersionManager's, which reads the same release history feed to
   * fill the project versions table. Sharing one key would let either
   * consumer starve the other with 304s.
   */
  private const string STATE_KEY_SUFFIX = ':core_versions';

  public function __construct(
      /**
       * The database.
       */
      private Connection $database,
      /**
       * The release history fetcher.
       */
      private Fetcher $fetcher,
  )
  {
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
   * Whether a release is one we know about.
   *
   * @param string $version
   *   The release, as the launch form submits it.
   */
  public function hasVersion(string $version): bool {
    $query = $this->database->select(self::TABLE_NAME);
    $query->addField(self::TABLE_NAME, 'version');
    $query->condition('version', $version);
    return $query->countQuery()->execute()->fetchField() > 0;
  }

  /**
   * Gets core versions satisfying a core compatibility constraint.
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
    return array_values(array_filter($versions, static fn(\stdClass $row) => Semver::satisfies($row->version, $constraint)));
  }

  /**
   * Updates stored Drupal core release data for a major version.
   *
   * Sourced from the updates.drupal.org release history feed with conditional
   * requests, never the www.drupal.org JSON API: the JSON API required a
   * paginated crawl per major, and drupal.org blocks the endpoint for
   * data-center IPs that hammer it.
   *
   * The "current" channel carries every Drupal 8+ major in one document, so
   * a single unmodified-since miss refreshes them all; later calls for other
   * majors then short-circuit on the 304.
   *
   * @param int $major_version
   *   The major version (7, 8, 9, etc.)
   */
  public function updateData(int $major_version): void {
    if ($major_version < 7) {
      throw new \InvalidArgumentException("The major version '$major_version' is not supported");
    }
    $channel = $major_version === 7 ? '7.x' : 'current';
    try {
      $release_xml = $this->fetcher->getProjectData('drupal', $channel, self::STATE_KEY_SUFFIX);
    }
    catch (ReleaseHistoryNotModifiedException) {
      return;
    }
    try {
      $data = Processor::getData($release_xml);
    }
    catch (NoReleaseHistoryFoundException) {
      return;
    }

    $majors = [];
    foreach ($data['releases'] as $release) {
      assert($release instanceof ProjectRelease);
      $parsed = self::parseVersion($release->version);
      if ($parsed === NULL) {
        continue;
      }
      [$major, $minor, $patch, $extra] = $parsed;
      $this->database->merge(self::TABLE_NAME)
        ->keys(['version' => $release->version])
        ->fields([
          'major' => $major,
          'minor' => $minor,
          'patch' => $patch,
          'extra' => $extra,
          'vcs_label' => $release->tag,
          'insecure' => (int) $release->isInsecure(),
        ])
        ->execute();
      $majors[$major] = TRUE;
    }
    Cache::invalidateTags(array_map(
      static fn (int $major) => "core_versions:$major",
      array_keys($majors)
    ));
  }

  /**
   * Parses a core version string into major, minor, patch, and extra.
   *
   * Handles semantic core versions (11.4.5, 10.0.0-beta1), branch versions
   * (11.x-dev, 11.4.x-dev), and Drupal 7 versions (7.103, 7.x-dev), matching
   * the field semantics the Drupal.org JSON API used to provide.
   *
   * @return array{int, int, int|null, string|null}|null
   *   Major, minor, patch, extra; or NULL for an unparseable version.
   */
  private static function parseVersion(string $version): ?array {
    if (preg_match('/^(\d+)(?:\.(\d+|x))?(?:\.(\d+|x))?(?:-(.+))?$/', $version, $matches) !== 1) {
      return NULL;
    }
    $major = (int) $matches[1];
    $second = $matches[2] ?? '';
    $third = $matches[3] ?? '';
    $extra = ($matches[4] ?? '') !== '' ? $matches[4] : NULL;
    if ($third !== '') {
      $minor = $second === 'x' ? 0 : (int) $second;
      $patch = $third === 'x' ? NULL : (int) $third;
    }
    else {
      // Two-segment versions: Drupal 7 (7.103) or a bare branch (11.x). The
      // Drupal.org release fields treated the second segment as the minor
      // version with no patch, and the table's consumers sort on that.
      $minor = ($second === 'x' || $second === '') ? 0 : (int) $second;
      $patch = NULL;
    }
    return [$major, $minor, $patch, $extra];
  }

}
