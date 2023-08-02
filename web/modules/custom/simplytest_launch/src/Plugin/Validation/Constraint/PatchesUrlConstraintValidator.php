<?php

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\UrlValidator;

final class PatchesUrlConstraintValidator extends UrlValidator implements ContainerInjectionInterface {

  /**
   * The allowed hosts for patches.
   *
   * @var string[]
   */
  private $allowedHosts;

  /**
   * Constructs a new PatchesUrlConstraintValidator object.
   *
   * @param array $allowed_hosts
   *   The allowed hosts for patches.
   */
  public function __construct(array $allowed_hosts) {
    $this->allowedHosts = $allowed_hosts;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->getParameter('simplytest_launch.allowed_hosts')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    parent::validate($value, $constraint);
    $value = (string) $value;
    if ('' === $value) {
      return;
    }
    // It is already invalid.
    if ($this->context->getViolations()->count() > 0) {
      return;
    }

    $host = parse_url($value, \PHP_URL_HOST);
    if (!in_array($host, $this->allowedHosts, TRUE)) {
      $this->context->buildViolation('Patches must only originate from a Drupal.org domain.')
        ->setParameter('{{ value }}', $this->formatValue($value))
        ->setCode(Url::INVALID_URL_ERROR)
        ->addViolation();
    }
  }

}
