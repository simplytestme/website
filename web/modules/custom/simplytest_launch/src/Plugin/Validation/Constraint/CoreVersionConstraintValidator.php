<?php declare(strict_types=1);

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simplytest_projects\CoreVersionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class CoreVersionConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly CoreVersionManager $coreVersionManager,
  ) {
  }

  #[\Override]
  public static function create(ContainerInterface $container): self {
    return new self($container->get('simplytest_projects.core_version_manager'));
  }

  #[\Override]
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof CoreVersionConstraint);
    $version = (string) $value;
    // NotBlank already reports an empty value. One message is enough.
    if ($version === '') {
      return;
    }
    if (!$this->coreVersionManager->hasVersion($version)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('@version', $version)
        ->addViolation();
    }
  }

}
