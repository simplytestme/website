<?php
/**
 * @file
 * Loaded on all environments.
 */

$settings['config_sync_directory'] = '../config/sync';
$settings['class_loader_auto_detect'] = FALSE;
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;

// Private directory.
$settings['file_private_path'] = 'sites/default/files/private';

if (getenv('LAGOON_GIT_SHA')) {
  $settings['deployment_identifier'] = getenv('LAGOON_GIT_SHA');
}

// Fastly sits in front of Lagoon, so REMOTE_ADDR is a Fastly node, a
// different one from request to request, and the visitor's address is the
// one before Fastly's in X-Forwarded-For. Trusting Fastly's ranges lets
// Drupal walk the header back to the visitor, so flood limits and logs see
// the real client. A request that skips Fastly comes from an untrusted
// address, so its X-Forwarded-For is ignored and cannot be faked.
//
// Only X-Forwarded-For is trusted, so host and HTTPS detection are unchanged.
//
// Ranges from https://api.fastly.com/public-ip-list
if (getenv('LAGOON')) {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = [
    '23.235.32.0/20',
    '43.249.72.0/22',
    '103.244.50.0/24',
    '103.245.222.0/23',
    '103.245.224.0/24',
    '104.156.80.0/20',
    '140.248.64.0/18',
    '140.248.128.0/17',
    '146.75.0.0/17',
    '151.101.0.0/16',
    '157.52.64.0/18',
    '167.82.0.0/17',
    '167.82.128.0/20',
    '167.82.160.0/20',
    '167.82.224.0/20',
    '172.111.64.0/18',
    '185.31.16.0/22',
    '199.27.72.0/21',
    '199.232.0.0/16',
    '2a04:4e40::/32',
    '2a04:4e42::/32',
  ];
  $settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR;
}

if (getenv('LAGOON_ENVIRONMENT_TYPE') !== 'production') {
  /**
   * Skip file system permissions hardening.
   *
   * The system module will periodically check the permissions of your site's
   * site directory to ensure that it is not writable by the website user. For
   * sites that are managed with a version control system, this can cause problems
   * when files in that directory such as settings.php are updated, because the
   * user pulling in the changes won't have permissions to modify files in the
   * directory.
   */
  $settings['skip_permissions_hardening'] = TRUE;
}

$config['tugboat.settings']['token'] = getenv('TUGBOAT_TOKEN');
$config['tugboat.settings']['repository_id'] = getenv('TUGBOAT_REPOSITORY_ID');
$config['tugboat.settings']['repository_base'] = getenv('TUGBOAT_REPOSITORY_BASE');

// Ensure the project refresher leverages a unique queue.
$settings['queue_service_simplytest_projects_project_refresher'] = 'queue_unique.database';

$settings['http_client_config'] = [
  'headers' => [
    'User-Agent' => 'Simplytest/1.0 (+https://simplytest.me/)',
  ],
];
