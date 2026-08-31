<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Psr\Log\LoggerInterface;

/**
 * Seeds a fresh site with a starter set of projects.
 *
 * Ephemeral environments install with an empty simplytest_project table, and
 * the on-demand Drupal.org fetch from web pods is unreliable (drupal.org
 * blocks some data-center egress IPs). Seeding from a deploy task keeps the
 * launch form testable without depending on live fetches at request time.
 */
final readonly class ProjectSeeder {

  /**
   * Projects a fresh environment starts with.
   *
   * A small, popular set: enough to exercise the autocomplete, the version
   * selects, and the additional-projects flow.
   */
  public const array STARTER_PROJECTS = [
    'drupal',
    'admin_toolbar',
    'canvas',
    'devel',
    'gin',
    'metatag',
    'paragraphs',
    'pathauto',
    'token',
    'webform',
  ];

  public function __construct(
    private ProjectFetcher $projectFetcher,
    private ProjectVersionManager $projectVersionManager,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Imports the given projects and their release data.
   *
   * Safe to run repeatedly: existing projects are re-fetched and their
   * release data refreshed. A project that cannot be fetched is logged and
   * skipped so one failure does not abort the rest of the seed.
   *
   * @param list<string>|null $shortnames
   *   Project shortnames to seed, or NULL for the starter set.
   *
   * @return list<string>
   *   The shortnames that were seeded successfully.
   */
  public function seed(?array $shortnames = NULL): array {
    $seeded = [];
    foreach ($shortnames ?? self::STARTER_PROJECTS as $shortname) {
      if ($this->projectFetcher->fetchProject($shortname) === NULL) {
        $this->logger->warning('Seeding skipped %project: the project could not be fetched.', [
          '%project' => $shortname,
        ]);
        continue;
      }
      $this->projectVersionManager->updateData($shortname);
      $seeded[] = $shortname;
    }
    return $seeded;
  }

}
