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
   *   The base preview name: `drupal10`, `dashi`, and so on.
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
   *
   * @throws \Drupal\simplytest_tugboat\Exception\LaunchLimitExceededException
   *   When the client has launched as many sandboxes as the limit allows.
   */
  public function launchInstance($submission);

}
