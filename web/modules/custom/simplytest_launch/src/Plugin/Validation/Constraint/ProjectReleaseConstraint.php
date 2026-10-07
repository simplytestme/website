<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Only a stored release of the project may be launched.
 *
 * The launch form offers the releases stored by ProjectVersionManager, but the
 * launch endpoint accepts any JSON body. The version ends up in the sandbox's
 * Composer command, so it has to be a release we actually know about.
 */
#[Constraint(
  id: 'ProjectRelease',
  label: new TranslatableMarkup('Project release', [], ['context' => 'Validation']),
  type: ['project_info'],
)]
final class ProjectReleaseConstraint extends SymfonyConstraint {

  public string $message = 'There is no release of @project with the version @version.';

}
