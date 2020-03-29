<?php

namespace Drupal\simplytest_ocd\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a simplytest_ocd annotation object.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class SimplyTestOCD extends Plugin {

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
   * The id for ocd buttons.
   *
   * @var string
   */
  public $ocd_id;

  /**
   * The theme_key for yaml.
   *
   * @var string
   */
  public $theme_key;

}
