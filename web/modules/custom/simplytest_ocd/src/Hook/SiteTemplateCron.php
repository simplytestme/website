<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\simplytest_ocd\SiteTemplateImporter;

/**
 * Keeps the imported site template list current.
 */
final readonly class SiteTemplateCron {

  /**
   * How long an imported list is used before being refreshed.
   */
  public const int LIFETIME = 86400;

  public function __construct(
    private SiteTemplateImporter $importer,
    private StateInterface $state,
    private TimeInterface $time,
  ) {
  }

  /**
   * Implements hook_cron().
   *
   * Unlike the base previews, this runs in every environment. The list is what
   * the template picker renders, so a PR environment with an empty one has no
   * picker, and the cost is one small request a day.
   */
  #[Hook('cron')]
  public function cron(): void {
    $imported = (int) $this->state->get(SiteTemplateImporter::IMPORTED, 0);
    if ($this->time->getRequestTime() - $imported < self::LIFETIME) {
      return;
    }
    // The importer stamps the state key itself, and only on success, so a
    // failed fetch is retried on the next run rather than waiting a day.
    $this->importer->import();
  }

}
