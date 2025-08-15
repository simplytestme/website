<?php declare(strict_types=1);

namespace Drupal\simplytest_projects\ReleaseHistory;

use Composer\Semver\Semver;

/**
 * @property string $name
 * @property string $core_compatibility
 * @property string $version
 * @property string $tag
 * @property string $date
 * @property string $status
 *
 * @phpstan-type ReleaseTerms array{"Release type": list<string>}
 * @phpstan-type ReleaseData array{name: string, core_compatibility: string, version: string, tag: string, date: string, status: string, terms: ReleaseTerms}
 */
final class ProjectRelease {

  /**
   * @param ReleaseData $data
   */
  public function __construct(private array $data)
  {
  }

  public function __get($name) {
    return $this->data[$name] ?? NULL;
  }

  public function isInsecure(): bool {
    return in_array('Insecure', $this->data['terms']['Release type'], TRUE);
  }

  public function isCoreCompatible(string $core_version): bool {
    return Semver::satisfies($core_version, $this->data['core_compatibility']);
  }

}
