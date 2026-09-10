<?php

namespace Drupal\simplytest_launch\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Patches URL constraint.
 *
 * Drupal core does not provide Symfony's URL constraint by default, this adds
 * it for our patch URLs and enforces `https`.
 */
#[Constraint(
  id: "PatchesUrl",
  label: new TranslatableMarkup("Patches URL", [], ["context" => "Validation"]),
)]
final class PatchesUrlConstraint extends Url {

  /**
   * {@inheritdoc}
   */
  #[HasNamedArguments]
  public function __construct(
    ?string $message = NULL,
    array|string|null $protocols = NULL,
    ?bool $relativeProtocol = NULL,
    ?callable $normalizer = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
    ?bool $requireTld = NULL,
    ?string $tldMessage = NULL,
  ) {
    parent::__construct(
      message: $message,
      protocols: $protocols ?? ['https'],
      relativeProtocol: $relativeProtocol,
      normalizer: $normalizer,
      groups: $groups,
      payload: $payload,
      // Every allowed host is a Drupal.org domain, so a patch URL always has a
      // top-level domain. This is the Symfony 8 default; setting it explicitly
      // keeps the constraint off the deprecation path.
      requireTld: $requireTld ?? TRUE,
      tldMessage: $tldMessage,
    );
  }

}
