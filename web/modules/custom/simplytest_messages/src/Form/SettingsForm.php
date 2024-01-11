<?php

namespace Drupal\simplytest_messages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Simplytest Messages settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplytest_messages_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simplytest_messages.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $messageConfig = $this->config('simplytest_messages.settings');
    $form['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the Message Block'),
      '#description' => $this->t('If this checkbox is checked the message block will be visible on the front page.'),
      '#default_value' => $messageConfig->get('enable'),
    ];

    $form['message'] = [
      '#type' => 'fieldset',
      '#title' => $this
        ->t('Message'),
    ];

    $form['message']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $messageConfig->get('title'),
    ];
    $form['message']['icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Icon'),
      '#upload_location' => 'public://',
      '#default_value' => $messageConfig->get('icon'),
    ];
    $form['message']['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#default_value' => $messageConfig->get('body')['value'] ?? '',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('simplytest_messages.settings')
      ->set('enable', $form_state->getValue('enable'))
      ->set('title', $form_state->getValue('title'))
      ->set('icon', $form_state->getValue('icon'))
      ->set('body', $form_state->getValue('body'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
