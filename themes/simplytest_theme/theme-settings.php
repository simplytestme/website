<?php

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

function simplytest_theme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state, $form_id = NULL) {
  // Work-around for a core bug affecting admin themes. See issue #943212.
  if (isset($form_id)) {
    return;
  }

  $form['simplytest_info'] = array(
    '#markup' => '<h2><br/>Advanced Theme Settings</h2></div>',
    '#weight' => -11
  );
  $form['simplytest_info']['header_bg_file'] = array(
    '#type' => 'textfield',
    '#title' => t('URL of the header background image'),
    '#default_value' => theme_get_setting('header_bg_file'),
    '#description' => t('Enter a URL of the form (/sites/default/files/your-background.jpg). If the background image is bigger than the header area, it is clipped. If it\'s smaller than the header area, it is tiled to fill the header area. To remove the background image, blank this field and save the settings.'),
    '#size' => 40,
  );
  $form['simplytest_info']['header_bg'] = array(
    '#type' => 'file',
    '#title' => t('Upload header background image'),
    '#size' => 40,
    '#attributes' => array('enctype' => 'multipart/form-data'),
    '#description' => t('If you don\'t have direct access to the server, use this field to upload your header background image. Uploads limited to .png .gif .jpg .jpeg .apng .svg extensions'),
    '#element_validate' => array('_simplytest_theme_header_bg_validate'),
  );
}

/**
 * Check and save the uploaded header background image
 */
function _simplytest_theme_header_bg_validate($element, FormStateInterface $form_state) {
  global $base_url;

  $validators = array('file_validate_extensions' => array('png gif jpg jpeg apng svg'));
  $files = file_save_upload('header_bg', $validators, "public://", NULL, FileSystemInterface::EXISTS_REPLACE);

  if (!empty($files)) {
    // change file's status from temporary to permanent and update file database
    /** @var File $file */
    $file = $files[0];
    $file->setPermanent();
    $file->save();
    $file_url = file_create_url($file->getFileUri());
    $file_url = str_ireplace($base_url, '', $file_url);
    $form_state->setValue('header_bg_file', $file_url);
  }
}
