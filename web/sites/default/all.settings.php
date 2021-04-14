<?php
/**
 * @file
 * Loaded on all environments.
 */

$settings['config_sync_directory'] = 'profiles/contrib/simplytest/config/sync';
$settings['class_loader_auto_detect'] = FALSE;
$settings['file_chmod_directory'] = 0775;
$settings['file_chmod_file'] = 0664;

// Private directory.
$settings['file_private_path'] = 'sites/default/files/private';

if (getenv('LAGOON_GIT_SHA')) {
  $settings['deployment_identifier'] = getenv('LAGOON_GIT_SHA');
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
