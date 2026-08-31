<?php

namespace Drupal\simplytest_ocd\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a OneClickDemo annotation object.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class OneClickDemo extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the ocd button.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The base Tugboat preview name.
   *
   * @var string
   */
  public $base_preview_name;

  /**
   * One-sentence description shown on the demo tile.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Sort weight for the tile grid; lower weights render first.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * Whether the tile is highlighted as the recommended demo.
   *
   * @var bool
   */
  public $recommended = FALSE;

}
