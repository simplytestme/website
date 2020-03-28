<?php

namespace Drupal\simplytest_tugboat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Simplytest tugboat settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplytest_tugboat_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simplytest_tugboat.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('simplytest_tugboat.settings');

    $form['github_api'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Github API Key'),
      '#default_value' => $config->get('github_api'),
    ];

    $form['github_ns'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Github Namespace'),
      '#default_value' => $config->get('github_ns'),
    ];

    $form['github_repo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Github Repo'),
      '#default_value' => $config->get('github_repo'),
    ];

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix for instances'),
      '#default_value' => $config->get('prefix'),
    ];

    $form['github_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Github user name'),
      '#default_value' => $config->get('github_username'),
    ];

    $form['github_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Github user email'),
      '#default_value' => $config->get('github_email'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo Add custom validation or remove this function completely.
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('simplytest_tugboat.settings')
         ->set('github_api', $form_state->getValue('github_api'))
         ->set('github_ns', $form_state->getValue('github_ns'))
         ->set('github_repo', $form_state->getValue('github_repo'))
         ->set('prefix', $form_state->getValue('prefix'))
         ->set('github_username', $form_state->getValue('github_username'))
         ->set('github_email', $form_state->getValue('github_email'))
         ->save();
    parent::submitForm($form, $form_state);
  }

}
