<?php

use Drupal\file\Entity\File;
use Drupal\file\Plugin\Core\Entity\FileInterface;
use Drupal\simplytest\Form\SimplyTestCallbacks;


/**
 * Implements hook_preprocess_node().
 */
function simplytest_theme_preprocess_node(&$variables){
  _simplytest_theme_images($variables);
}

/**
 * Implements hook_preprocess_page().
 */
function simplytest_theme_preprocess_page(&$variables, &$node){
  _simplytest_theme_images($variables);
}

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function simplytest_theme_form_system_theme_settings_alter(&$form, $form_state) {
  _simplytest_theme_settings($form, $form_state);
}

/**
 * Custom variables that holds the information about simplytest new theme settings fields.
 * @param $form
 * @param $form_state
 */
function _simplytest_theme_settings(&$form, $form_state){
  $form['simplytest_info'] = array(
    '#markup' => '<h2><br/>Advanced Theme Settings</h2></div>',
    '#weight' => -11
  );

  $fids = theme_get_setting('front_page_background_image' , 'simplytest');
  $background_fid = (!empty($fids) && file_load($fids[0])) ? $fids : '';
  $form['simplytest_info']['front_page_background_image'] = array(
    '#type' => 'managed_file',
    '#title' => 'Front Page Background Image',
    '#description' => 'Add background image for the front page',
    '#required' => FALSE,
    '#weight' => -10,
    '#upload_location' => file_default_scheme() . '://theme/backgrounds/',
    '#default_value' => $background_fid,
    '#upload_validators' => array(
      'file_validate_extensions' => array('gif png jpg jpeg'),
    ),
  );

  // Perform our custom submit before system submit
  $form['#submit'][] = array('Drupal\simplytest\Form\SimplyTestCallbacks', 'submitSettings');
}

function _simplytest_theme_images(&$variables){
// Make sure that this only is been load in the front page.
  if($variables['is_front'] == TRUE){
    $fid = theme_get_setting('front_page_background_image', 'simplytest');
    kint($fid);
    $file = NULL;
    if($fid){
      // Load the file.
      $file = File::load($fid[0]);
    }
    // Adding a validation otherwise is going to give you a white screen.
    if(!empty($file)) {
      // Generate the URL.
      $variables['front_page_background_image'] = $file->url();
    }
  }
}
