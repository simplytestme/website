<?php

// @codingStandardsIgnoreFile

/**
 * @file
 * Drupal site-specific configuration file.
 */

// Lando environment settings
if (getenv('LANDO_INFO')) {
  $lando_info = json_decode(getenv('LANDO_INFO'), TRUE);

  // Allow any domains to access the site with Lando.
  $settings['trusted_host_patterns'] = [
    '^(.+)$',
  ];

  $databases['default']['default'] = [
    'database' => $lando_info['database']['creds']['database'],
    'username' => $lando_info['database']['creds']['user'],
    'password' => $lando_info['database']['creds']['password'],
    'prefix' => '',
    'host' => $lando_info['database']['internal_connection']['host'],
    'port' => $lando_info['database']['internal_connection']['port'],
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => 'mysql',
  ];

  $settings['hash_salt'] = 'mYFN2M9k6xljq3SUDINzXTOmR0nT210j0FEjClbsfl3empeU_lIvJApGK2tv3fcFWyuHSZNUPQ';
}
