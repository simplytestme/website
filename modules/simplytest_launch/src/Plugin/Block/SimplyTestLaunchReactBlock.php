<?php

namespace Drupal\simplytest_launch\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Book navigation' block.
 *
 * @Block(
 *   id = "simplytest_launch_react",
 *   admin_label = @Translation("SimplyTest Launch React")
 * )
 */
class SimplyTestLaunchReactBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'component_id' => 'root',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['component_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Component Id'),
      '#default_value' => $this->configuration['component_id'],
      '#description' => $this->t("This will be set as id attribute of the component."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['component_id'] = $form_state->getValue('component_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'simplytest_react_component',
      '#component_id' => $this->configuration['component_id'],
      '#attached' => [
        'library' => [
          'simplytest_theme/react',
        ],
      ],
    ];
  }

}
