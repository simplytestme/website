<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simplytest_launch\TypedData\ProjectInfoDefinition;
use Drupal\simplytest_projects\ProjectVersionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ProjectReleaseConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly ProjectVersionManager $projectVersionManager,
  ) {
  }

  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self($container->get('simplytest_projects.project_version_manager'));
  }

  #[\Override]
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof ProjectReleaseConstraint);
    $project = is_array($value) ? (string) ($value['shortname'] ?? '') : '';
    $version = is_array($value) ? (string) ($value['version'] ?? '') : '';
    // NotBlank and Regex already report these. One message is enough.
    if ($version === '' || preg_match(ProjectInfoDefinition::SHORTNAME_PATTERN, $project) !== 1) {
      return;
    }
    $versions = array_map(
      static fn(\stdClass $release): string => $release->version,
      $this->projectVersionManager->getAllReleases($project),
    );
    if (!in_array($version, $versions, TRUE)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('@project', $project)
        ->setParameter('@version', $version)
        ->atPath('version')
        ->addViolation();
    }
  }

}
