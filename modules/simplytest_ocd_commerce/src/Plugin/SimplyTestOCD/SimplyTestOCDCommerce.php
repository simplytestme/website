<?php

namespace Drupal\simplytest_ocd_commerce\Plugin\SimplyTestOCD;

use Drupal\simplytest_ocd\SimplyTestOCDInterface;

/**
 * Provides one click demo for commerce.
 *
 * @SimplyTestOCD(
 *   id = "simplytet_ocd_commerce",
 *   title = @Translation("Drupal Commerce Demo"),
 *   ocd_id = "commerce",
 *   theme_key = "commerce"
 * )
 */
class SimplyTestOCDCommerce implements SimplyTestOCDInterface {}
