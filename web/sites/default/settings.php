<?php

/**
 * @file
 * Lagoon Drupal settings file.
 *
 * The primary settings file for Drupal which includes
 * settings.lagoon.php that contains Lagoon-specific settings.
 */

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Lagoon-specific settings file.
 *
 * N.B. The settings.lagoon.php file makes some changes
 *      that affect all environments that this site
 *      exists in.  Always include this file, even in
 *      a local development environment, to ensure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.lagoon.php";

$settings['config_sync_directory'] = '../config';
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');
$settings['deployment_identifier'] = getenv('DEPLOYMENT_IDENTIFIER') ?: \Drupal::VERSION;
$settings['class_loader_auto_detect'] = FALSE;
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;
$settings['file_private_path'] = '../private';

$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
  'vendor',
];

$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;


// Automatic inclusion of DDEV settings.
if (file_exists($app_root . '/' . $site_path . '/settings.ddev.php')) {
  include $app_root . '/' . $site_path . '/settings.ddev.php';
}

// Automatic inclusion of local settings.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
   include $app_root . '/' . $site_path . '/settings.local.php';
}

// Last: This server specific services file.
if (file_exists(__DIR__ . '/services.local.yml')) {
  $settings['container_yamls'][] = __DIR__ . '/services.local.yml';
}
