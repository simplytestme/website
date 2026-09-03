<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;

final class InstanceLaunchDefinition extends ComplexDataDefinitionBase {

  /**
   * The install profiles a launch may ask for.
   *
   * These are the options the launch form offers, in
   * web/themes/simplytest_theme/lib/components/InstallationOptions.jsx. The
   * value is interpolated into the sandbox's `drush si` command and shown on
   * the public launch statistics, so nothing else may get through.
   */
  public const array INSTALL_PROFILES = ['standard', 'minimal', 'demo_umami'];

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create($type = 'instance_launch') {
    $definition['type'] = $type;
    return new self($definition);
  }

  #[\Override]
  public function getPropertyDefinitions() {
    $properties = [];
    $properties['project'] = ProjectInfoDefinition::create()
      ->setLabel(new TranslatableMarkup('Project details'))
      ->addConstraint('ComplexData')
      ->setRequired(TRUE);
    $properties['drupalVersion'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Drupal version'))
      ->addConstraint('NotBlank')
      ->addConstraint('PrimitiveType')
      ->addConstraint('CoreVersion');
    $properties['installProfile'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Install profile'))
      ->addConstraint('PrimitiveType')
      // Rejects a blank value as well, so NotBlank would only repeat it.
      ->addConstraint('Choice', [
        'choices' => self::INSTALL_PROFILES,
        'message' => 'The install profile must be one of ' . implode(', ', self::INSTALL_PROFILES) . '.',
      ])
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
