<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * @DataType(
 *   id = "project_info",
 *   label = @Translation("Project info"),
 *   definition_class = "\Drupal\simplytest_launch\TypedData\ProjectInfoDefinition"
 * )
 */

final class ProjectInfo extends Map {

  /**
   * The value.
   *
   * @var array
   *
   * @note ::getValue() assumes the `value` property, but it doesn't exist.
   */
  protected $value = [];


}
