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
   * @param string $group
   *   Which list the plugin belongs to. `demo` is a tile on the home page;
   *   `site_template` is a card in the template picker. They launch the same
   *   way and are kept apart only in the UI.
   * @param bool $clone_base
   *   Whether a launch is a straight clone of the base preview. TRUE when the
   *   base preview is the finished demo, which is the case for a demo that
   *   owns its base. FALSE when the base is only a starting point shared with
   *   other demos, and the launch still has work to do on top of it.
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
    public readonly string $group = 'demo',
    public readonly bool $clone_base = TRUE,
    public readonly ?string $deriver = NULL,
  ) {}

}
