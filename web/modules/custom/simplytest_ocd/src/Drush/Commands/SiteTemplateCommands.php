<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Drush\Commands;

use Drupal\simplytest_ocd\SiteTemplateImporter;
use Drupal\simplytest_ocd\SiteTemplateRepository;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Imports and inspects the site template list.
 *
 * Cron does the import. These exist for seeding a fresh environment, which
 * installs with an empty list, and for seeing what the import made of the
 * curated file.
 */
final class SiteTemplateCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly SiteTemplateImporter $importer,
    private readonly SiteTemplateRepository $repository,
  ) {
    parent::__construct();
  }

  /**
   * Imports Drupal CMS's curated list of site templates.
   */
  #[CLI\Command(name: 'simplytest:site-templates:import', aliases: ['stst-import'])]
  public function import(): int {
    $count = $this->importer->import();
    if ($count < 0) {
      $this->io()->error('The list could not be read; the stored one was left alone. See the log.');
      return self::EXIT_FAILURE;
    }
    $this->io()->success(sprintf('Imported %d site template(s).', $count));
    return self::EXIT_SUCCESS;
  }

  /**
   * Lists the imported site templates.
   */
  #[CLI\Command(name: 'simplytest:site-templates:list', aliases: ['stst-list'])]
  public function list(): void {
    $rows = [];
    foreach ($this->repository->all() as $machine_name => $template) {
      $rows[] = [
        $machine_name,
        $template->name,
        $template->package,
        'recipes/' . $template->recipe,
        $template->creator ?? '',
        $template->screenshot === NULL ? 'no' : 'yes',
      ];
    }
    if ($rows === []) {
      $this->io()->warning('No site templates imported yet. Run simplytest:site-templates:import.');
      return;
    }
    $this->io()->table(['Machine name', 'Name', 'Package', 'Recipe', 'Creator', 'Screenshot'], $rows);
  }

}
