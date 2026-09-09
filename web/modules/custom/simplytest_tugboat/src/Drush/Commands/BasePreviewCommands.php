<?php

declare(strict_types=1);

namespace Drupal\simplytest_tugboat\Drush\Commands;

use Drupal\simplytest_tugboat\BasePreviewManager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages the Tugboat base previews by hand.
 *
 * Cron does the same work on production. These exist for checking what is on
 * Tugboat, and for rebuilding a base without waiting a day.
 */
final class BasePreviewCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'simplytest_tugboat.base_preview_manager')]
    private readonly BasePreviewManager $basePreviews,
  ) {
    parent::__construct();
  }

  /**
   * Lists the previews on Tugboat for every base name.
   */
  #[CLI\Command(name: 'simplytest:tugboat:base-previews:list', aliases: ['stbp-list'])]
  public function list(): void {
    $rows = [];
    foreach ($this->basePreviews->inventory() as $name => $previews) {
      if ($previews === []) {
        $rows[] = [$name, '(none)', '', '', ''];
        continue;
      }
      foreach ($previews as $preview) {
        $rows[] = [
          $name,
          $preview['id'],
          $preview['state'],
          $preview['createdAt'],
          (string) count($preview['children']),
        ];
      }
    }
    $this->io()->table(['Base', 'Preview', 'State', 'Created', 'Children'], $rows);
  }

  /**
   * Starts a fresh build of a base preview, or of every base.
   */
  #[CLI\Command(name: 'simplytest:tugboat:base-previews:rebuild', aliases: ['stbp-rebuild'])]
  #[CLI\Argument(name: 'name', description: 'A base name such as drupal10 or umami. Rebuilds every base when omitted.')]
  public function rebuild(?string $name = NULL): int {
    if ($name === NULL) {
      $started = $this->basePreviews->rebuildAll();
    }
    elseif (!in_array($name, $this->basePreviews->names(), TRUE)) {
      $this->io()->error(sprintf('Unknown base "%s". Known bases: %s', $name, implode(', ', $this->basePreviews->names())));
      return self::EXIT_FAILURE;
    }
    else {
      $started = [$name => $this->basePreviews->rebuild($name)];
    }

    $failed = FALSE;
    foreach ($started as $base => $preview_id) {
      if ($preview_id === NULL) {
        $this->io()->error(sprintf('%s: Tugboat refused the build, see the log.', $base));
        $failed = TRUE;
        continue;
      }
      $this->io()->success(sprintf('%s: building as %s', $base, $preview_id));
    }
    return $failed ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }

  /**
   * Deletes base previews that a newer build has replaced.
   */
  #[CLI\Command(name: 'simplytest:tugboat:base-previews:prune', aliases: ['stbp-prune'])]
  public function prune(): void {
    $deleted = $this->basePreviews->prune();
    if ($deleted === []) {
      $this->io()->success('Nothing to prune.');
      return;
    }
    $this->io()->success(sprintf('Deleted %d previews: %s', count($deleted), implode(', ', $deleted)));
  }

}
