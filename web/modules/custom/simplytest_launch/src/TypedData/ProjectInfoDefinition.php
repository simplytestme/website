<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

final class ProjectInfoDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'project_info') {
    $definition['type'] = $type;
    return new self($definition);
  }


  public function getPropertyDefinitions() {
    $properties = [];
    $properties['title'] = DataDefinition::create('string')
      ->addConstraint('PrimitiveType')
      ->setLabel(new TranslatableMarkup('Title'));
    $properties['shortname'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Short name (machine name'))
      ->addConstraint('Regex', [
        'pattern' => '/^[a-z0-9_]+$/',
      ])
      ->addConstraint('NotBlank')
      ->addConstraint('PrimitiveType')
      ->setRequired(TRUE);
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Extension type'));
    $properties['sandbox'] = DataDefinition::create('boolean')
      // NotBlank considers `false` to be invalid.
      ->addConstraint('PrimitiveType')
      // This considers `false` to be invalid.
      // ->setRequired(TRUE)
      ->setLabel(new TranslatableMarkup('Sandbox'));
    $properties['version'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Version'))
      ->addConstraint('NotBlank')
      ->addConstraint('PrimitiveType')
      ->setRequired(TRUE);
    // Use `string` over `uri` for better validation control.
    // The `uri` data type just returns "invalid primitive type".
    $properties['patches'] = ListDataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Patches to apply'));
    $properties['patches']->getItemDefinition()
      ->addConstraint('PatchesUrl');
    return $properties;
  }

}
