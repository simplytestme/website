<?php

namespace Drupal\simplytest_tugboat;

/**
 * InstanceManager service.
 */
interface InstanceManagerInterface {

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
