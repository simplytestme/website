<?php

namespace Drupal\simplytest_projects\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class Settings extends ConfigFormBase {

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'simplytest_projects.settings',
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'simplytest_projects_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('simplytest_projects.settings');

    $form['settings_version'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Project versioning settings'),
    );
    $form['settings_version']['version_timeout'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Maximum age of version data'),
      '#default_value' => $config->get('version_timeout', '-1 hour'),
      '#description' => $this->t('Example: %example', array('%example' => '-1 hour')),
    );

    $form['settings_blacklists'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Blacklisting'),
    );
    $form['settings_blacklists']['blacklisted_projects'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Blacklisted project'),
      '#description' => $this->t('A list of project shortnames to disable.'),
      '#default_value' => implode(PHP_EOL, $config->get('blacklisted_projects')),
    );
    $form['settings_blacklists']['blacklisted_versions'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Blacklisted versions'),
      '#description' => $this->t('A list of regular expressions for versions to disable.'),
      '#default_value' => implode(PHP_EOL, $config->get('blacklisted_versions')),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $blacklisted_projects = explode(PHP_EOL, $form_state->getValue('blacklisted_projects'));
    $blacklisted_versions = explode(PHP_EOL, $form_state->getValue('blacklisted_versions'));

    foreach ($blacklisted_projects as $key => &$project) {
      $project = trim($project);
      if (empty($project)) {
        unset($blacklisted_projects[$key]);
      }
    }
    foreach ($blacklisted_versions as $key => &$version) {
      $version = trim($version);
      if (empty($version)) {
        unset($blacklisted_versions[$key]);
      }
    }

    $this->config('simplytest_projects.settings')
      ->set('version_timeout', $form_state->getValue('version_timeout'))
      ->set('blacklisted_projects', $blacklisted_projects)
      ->set('blacklisted_versions', $blacklisted_versions)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
