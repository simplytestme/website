<?php

/**
 * @file
 * Settings for the config:status CI job.
 *
 * Copied to web/sites/default/settings.local.php, which is gitignored, so the
 * database connection survives the job's second checkout. settings.php is
 * tracked, and anything drush site:install wrote there would be reverted.
 */

$databases['default']['default'] = [
  'driver' => 'mysql',
  'database' => 'db',
  'username' => 'root',
  'password' => '',
  'host' => '127.0.0.1',
  'port' => getenv('DB_PORT') ?: 3306,
  'prefix' => '',
];

$settings['hash_salt'] = 'ci';
