<?php

namespace Drupal\simplytest_tugboat;

/**
 * InstanceManager service.
 */
interface InstanceManagerInterface {

  /**
   * Finds the base preview a sandbox builds on.
   *
   * @param string $context
   *   The base preview name: `drupal10`, `umami`, and so on.
   *
   * @return string
   *   The preview ID, or `none` when no usable base exists. Tugboat reads
   *   `none` as "build from scratch".
   */
  public function loadPreviewId(string $context): string;

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
