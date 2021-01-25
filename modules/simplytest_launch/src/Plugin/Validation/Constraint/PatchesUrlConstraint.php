<?php

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\UrlValidator;

/**
 * Patches URL constraint.
 *
 * Drupal core does not provide Symfony's URL constraint by default, this adds
 * it for our patch URLs and enforces `https`.
 *
 * @Constraint(
 *   id = "PatchesUrl",
 *   label = @Translation("Patches URL", context = "Validation")
 * )
 */
final class PatchesUrlConstraint extends Url {

  /**
   * {@inheritdoc}
   */
  public function __construct($options = []) {
    $options += ['protocols' => ['https']];
    parent::__construct($options);
  }

}
