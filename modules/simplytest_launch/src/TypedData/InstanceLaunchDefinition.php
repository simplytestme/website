<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

final class InstanceLaunchDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'instance_launch') {
    $definition['type'] = $type;
    return new self($definition);
  }

  public function getPropertyDefinitions() {
    $properties = [];
    $properties['project'] = ProjectInfoDefinition::create()
      ->setLabel(new TranslatableMarkup('Project details'))
      ->addConstraint('ComplexData')
      ->setRequired(TRUE);
    $properties['drupalVersion'] = DataDefinition::create('string')
      // @todo add a custom constraint validating a legit version.
      ->setLabel(new TranslatableMarkup('Drupal version'))
      ->addConstraint('NotBlank')
      ->addConstraint('PrimitiveType')
      ->setRequired(TRUE);
    $properties['installProfile'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Install profile'))
      ->addConstraint('NotBlank')
      ->addConstraint('PrimitiveType')
      ->setRequired(TRUE);
    $properties['manualInstall'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Manual installation'))
      ->addConstraint('PrimitiveType')
      ->setRequired(TRUE);
    $properties['additionalProjects'] = ListDataDefinition::create('project_info')
      ->setLabel(new TranslatableMarkup('Additional projects'));
    return $properties;
  }

}
