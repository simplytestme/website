<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplytest_ocd\SiteTemplateRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Turns each imported site template into a launchable plugin.
 *
 * The templates come from Drupal CMS's curated list, which changes without us
 * deploying anything, so there is no class per template. Only the stored copy
 * is read here: this runs during plugin discovery.
 *
 * @see \Drupal\simplytest_ocd\SiteTemplateImporter
 */
final class SiteTemplateDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    private readonly SiteTemplateRepository $repository,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new self($container->get(SiteTemplateRepository::class));
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $base_plugin_definition
   *
   * @phpstan-return array<string, array<string, mixed>>
   */
  #[\Override]
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->repository->all() as $machine_name => $template) {
      // A deriver's definitions replace the base rather than extending it, so
      // every key the launch and the endpoint read has to be here.
      $this->derivatives[$machine_name] = [
        'id' => $base_plugin_definition['id'] . ':' . $machine_name,
        'title' => new TranslatableMarkup('@name', ['@name' => $template->name]),
        'description' => new TranslatableMarkup('@description', ['@description' => $template->description]),
        'template' => $template->toArray(),
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
