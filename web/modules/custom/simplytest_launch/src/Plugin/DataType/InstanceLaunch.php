<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition;

#[DataType(
  id: "instance_launch",
  label: new TranslatableMarkup("Instance launch"),
  definition_class: InstanceLaunchDefinition::class,
)]
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
