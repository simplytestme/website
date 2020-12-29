<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

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
      ->addConstraint('NotBlank')
      ->addConstraint('PrimitiveType')
      ->setLabel(new TranslatableMarkup('Extension type'))
      ->setRequired(TRUE);
    $properties['sandbox'] = DataDefinition::create('boolean')
      // NotBlank considers `false` to be invalid.
      ->addConstraint('PrimitiveType')
      // This considers `false` to be invalid.
      // ->setRequired(TRUE)
      ->setLabel(new TranslatableMarkup('Sandbox'));
    return $properties;
  }

}
