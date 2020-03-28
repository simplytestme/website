<?php

namespace Drupal\simplytest_tugboat;

/**
 * InstanceManager service.
 */
interface InstanceManagerInterface {

  /**
   * Running submission states.
   */
  const ENQUEUE = 100;
  const SPAWNED = 110;
  const PREPARE = 120;
  const DOWNLOAD = 130;
  const PATCHING = 140;
  const INSTALLING = 150;
  const FINALIZE = 160;
  const FINISHED = 170;

  /**
   * Terminated submission states.
   */
  const TERMINATED = 200;
  const ABORTED = 210;
  const FAILED = 220;

  /**
   * Failure submission states. One error for each running state.
   */
  const ERROR_SERVER = 300;
  const ERROR_SPAWNED = 310;
  const ERROR_PREPARE = 320;
  const ERROR_DOWNLOAD = 330;
  const ERROR_PATCHING = 340;
  const ERROR_INSTALLING = 350;
  const ERROR_FINALIZE = 360;

  /**
   * Get instance log.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   *
   * @return string
   *   The Tugboat log messages, combined into one string.
   */
  public function getLog($instance_id);

  /**
   * Loads the preview ID from a base preview branch.
   *
   * @param string $context
   *   Context?
   * @param bool $base
   *   Whether to prefix $context with "base-".
   *
   * @return string
   *   The ID of the preview.
   */
  public function loadPreviewId($context, $base = TRUE);

  /**
   * Loads the Tugboat URL for an instance.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   *
   * @return string
   *   The Tugboat URL or an empty string if not found.
   */
  public function loadUrl($instance_id);

  /**
   * Updates the Tugboat URL for an instance.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   * @param string $tugboat_url
   *   The Tugboat URL.
   */
  public function updateUrl($instance_id, $tugboat_url);

  /**
   * Loads the context for an instance.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   *
   * @return string
   *   The context or an empty string if not found.
   */
  public function loadContext($instance_id);

  /**
   * Creates an entity for the given instance, setting the context.
   *
   * @param $instance_id
   *   The primary identifier for the instance.
   * @param $context
   *   The context.
   */
  public function createWithContext($instance_id, $context);

  /**
   * Update instance status.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   * @param int $status
   *   One of the constants from InstanceManagerInterface.
   */
  public function updateStatus($instance_id, $status);

  /**
   * Instance state as array.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   *
   * @return array
   *   Information about the current status: the keys are
   *   - code (int)
   *   - percent (string)
   *   - message (string)
   *   - log (string[])
   */
  public function getStatusState($instance_id);

  /**
   * Callback for the tugboat launch instance.
   *
   * @param array $submission
   *   An array describing a Drupal project. The  following keys are used:
   *   - additionals
   *   - bypass_install
   *   - patches
   *   - project: defaults to 'drupal'
   *   - stm_one_click_demo
   *   - version
   */
  public function launchInstance($submission);

}
