<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Stores the curated list of site templates.
 *
 * Key/value, not config and not cache. Not config because the list is
 * somebody else's data, and exporting it would put an upstream edit into
 * `config/sync` and fail the config status check. Not cache because a cache
 * flush would empty the template picker until the next cron run.
 *
 * The whole list lives under one key. It is replaced wholesale on every
 * import, so a template withdrawn upstream stops being offered instead of
 * lingering as an orphaned row.
 */
final readonly class SiteTemplateRepository {

  /**
   * The key/value collection holding the list.
   */
  public const string COLLECTION = 'simplytest_ocd.site_templates';

  /**
   * The key holding the list.
   */
  private const string KEY = 'templates';

  public function __construct(
    private KeyValueFactoryInterface $keyValueFactory,
  ) {
  }

  /**
   * Every site template that can be launched.
   *
   * @return array<string, \Drupal\simplytest_ocd\SiteTemplateInfo>
   *   The templates, keyed by machine name.
   */
  public function all(): array {
    try {
      $stored = $this->keyValueFactory->get(self::COLLECTION)->get(self::KEY, []);
    }
    catch (DatabaseExceptionWrapper) {
      // Plugin discovery reads this, and discovery runs during site install
      // before the key/value table exists. An empty list there is correct: no
      // templates have been imported yet.
      return [];
    }
    if (!is_array($stored)) {
      return [];
    }
    $templates = [];
    foreach ($stored as $machine_name => $values) {
      $templates[$machine_name] = SiteTemplateInfo::fromArray($values);
    }
    return $templates;
  }

  /**
   * Replaces the stored list.
   *
   * @param array<string, \Drupal\simplytest_ocd\SiteTemplateInfo> $templates
   *   The templates, keyed by machine name.
   */
  public function save(array $templates): void {
    $this->keyValueFactory->get(self::COLLECTION)->set(
      self::KEY,
      array_map(static fn (SiteTemplateInfo $t): array => $t->toArray(), $templates),
    );
  }

}
