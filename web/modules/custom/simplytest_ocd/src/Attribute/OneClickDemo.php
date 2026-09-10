<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a OneClickDemo attribute object.
 *
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class OneClickDemo extends Plugin {

  /**
   * Constructs a OneClickDemo attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title of the ocd button.
   * @param string $base_preview_name
   *   The base Tugboat preview name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   One-sentence description shown on the demo tile.
   * @param int $weight
   *   Sort weight for the tile grid; lower weights render first.
   * @param bool $recommended
   *   Whether the tile is highlighted as the recommended demo.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $title,
    public readonly string $base_preview_name,
    public readonly TranslatableMarkup $description,
    public readonly int $weight = 0,
    public readonly bool $recommended = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
