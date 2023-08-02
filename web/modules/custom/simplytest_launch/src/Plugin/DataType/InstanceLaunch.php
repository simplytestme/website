<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * @DataType(
 *   id = "instance_launch",
 *   label = @Translation("Instance launch"),
 *   definition_class = "\Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition"
 * )
 */

final class InstanceLaunch extends Map {

  /**
   * The value.
   *
   * @var array
   *
   * @note ::getValue() assumes the `value` property, but it doesn't exist.
   */
  protected $value = [];


}
