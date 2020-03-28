<?php

namespace Drupal\simplytest_tugboat\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Instance status' formatter.
 *
 * @FieldFormatter(
 *   id = "simplytest_tugboat_instance_status",
 *   label = @Translation("Instance status"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class InstanceStatusFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The instance manager service.
   *
   * @var \Drupal\simplytest_tugboat\InstanceManagerInterface
   */
  protected $instanceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $instance->instanceManager = $container->get('simplytest_tugboat.instance_manager');
    return instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_format' => 'status_label',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $elements['display_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Display format'),
      '#default_value' => $this->getSetting('display_format'),
      '#options' => [
        'status_code' => $this->t('Status code'),
        'status_label' => $this->t('Status label'),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = $this->t('Format: @format', ['@format' => $this->getSetting('display_format')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    if ($this->getSetting('display_format') == 'status_label') {
      foreach ($items as $delta => $item) {
        $status_array = $this->instanceManager->getStatus($item->value);
        $element[$delta] = [
          '#markup' =>  $status_array ? $status_array['label'] : $item->value,
        ];
      }
    }
    else {
      foreach ($items as $delta => $item) {
        $element[$delta] = [
          '#markup' => $item->value,
        ];
      }
    }

    return $element;
  }

}
