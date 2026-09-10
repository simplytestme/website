<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Map;
use Drupal\simplytest_launch\TypedData\ProjectInfoDefinition;

#[DataType(
  id: "project_info",
  label: new TranslatableMarkup("Project info"),
  definition_class: ProjectInfoDefinition::class,
)]
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
