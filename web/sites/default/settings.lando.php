<?php

// @codingStandardsIgnoreFile

/**
 * @file
 * Drupal site-specific configuration file.
 */

// Lando environment settings
if (getenv('LANDO_INFO')) {
  $lando_info = json_decode(getenv('LANDO_INFO'), TRUE);

  // Files directory paths.
  $settings['file_public_path'] = 'sites/default/files';
  $settings['file_private_path'] = 'sites/default/files/private';

  // This setting is specific to Lando.
  $config_directories[CONFIG_SYNC_DIRECTORY] = '/app/config/common';

  $settings['config_sync_directory'] = '/app/config/common';
  $settings['file_temp_path'] = $_ENV['TEMP'];

  // Generic hash salt for all local environments.
  $settings['hash_salt'] = 'BfHE?EG)vJPa3uikBCZWW#ATbDLijMFRZgfkyayYcZYoy>eC7QhdG7qaB4hcm4x$';

  // Allow any domains to access the site with Lando.
  $settings['trusted_host_patterns'] = [
    '^(.+)$',
  ];

  // Enable Configuration Read-only Mode (Only on Prod & UAT)
  if (PHP_SAPI !== 'cli') {
    $settings['config_readonly'] = TRUE;
  }

  // Add default config split settings for local development.
  // $config['config_split.config_split.local']['status'] = TRUE;
  // $config['config_split.config_split.dev']['status'] = FALSE;
  // $config['config_split.config_split.uat']['status'] = FALSE;
  // $config['config_split.config_split.prod']['status'] = FALSE;

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

  // Check for PHP Memcached libraries.
  $memcache_exists = class_exists('Memcache', FALSE);
  $memcached_exists = class_exists('Memcached', FALSE);
  $memcache_module_is_present = file_exists(DRUPAL_ROOT . '/modules/contrib/memcache/memcache.services.yml');
  if ($memcache_module_is_present && ($memcache_exists || $memcached_exists)) {
    $settings['memcache']['servers'] = ['cache:11211' => 'default'];
    $settings['memcache']['bins'] = ['default' => 'default'];
    $settings['memcache']['key_prefix'] = 'site_prefix_';

    if (!InstallerKernel::installationAttempted()) {
      $settings['cache']['default'] = 'cache.backend.memcache';
    }
  }
}
