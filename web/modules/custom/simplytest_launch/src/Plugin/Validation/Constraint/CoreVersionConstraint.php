<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Only known Drupal core releases may be launched.
 *
 * The launch form offers the releases stored by CoreVersionManager, but the
 * launch endpoint accepts any JSON body. Whatever is submitted here ends up in
 * the sandbox's Composer command and in the public launch statistics, so it has
 * to be a release we actually know about.
 */
#[Constraint(
  id: 'CoreVersion',
  label: new TranslatableMarkup('Drupal core version', [], ['context' => 'Validation']),
)]
final class CoreVersionConstraint extends SymfonyConstraint {

  public string $message = 'There is no Drupal core release with the version @version.';

}
