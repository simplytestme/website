<?php

namespace Drupal\simplytest_ocd_umami\Plugin\SimplyTestOCD;

use Drupal\simplytest_ocd\SimplyTestOCDInterface;

/**
 * Provides one click demo for umami.
 *
 * @SimplyTestOCD(
 *   id = "simplytet_ocd_umami",
 *   title = @Translation("Drupal Umami Demo"),
 *   ocd_id = "umami",
 *   theme_key = "umami"
 * )
 */
class SimplyTestOCDUmami implements SimplyTestOCDInterface {}
