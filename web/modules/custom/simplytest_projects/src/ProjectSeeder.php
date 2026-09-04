<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Seeds a fresh site with a starter set of projects.
 *
 * Ephemeral environments install with an empty simplytest_project table.
 * Seeding from a deploy task keeps the launch form testable without
 * depending on live fetches at request time.
 */
final readonly class ProjectSeeder {

  /**
   * Projects a fresh environment starts with.
   *
   * A small, popular set: enough to exercise the autocomplete, the version
   * selects, and the additional-projects flow. Title and type are duplicated
   * here deliberately: they come from drupal.org's JSON API, which
   * intermittently blocks data-center IPs, so the seeder must not depend on
   * it. Release data comes from updates.drupal.org, which is not blocked.
   *
   * @var array<string, array{title: string, type: string}>
   */
  public const array STARTER_PROJECTS = [
    'drupal' => ['title' => 'Drupal core', 'type' => ProjectTypes::CORE],
    'admin_toolbar' => ['title' => 'Admin Toolbar', 'type' => ProjectTypes::MODULE],
    'canvas' => ['title' => 'Drupal Canvas', 'type' => ProjectTypes::MODULE],
    'devel' => ['title' => 'Devel', 'type' => ProjectTypes::MODULE],
    'gin' => ['title' => 'Gin Admin Theme', 'type' => ProjectTypes::THEME],
    'metatag' => ['title' => 'Metatag', 'type' => ProjectTypes::MODULE],
    'paragraphs' => ['title' => 'Paragraphs', 'type' => ProjectTypes::MODULE],
    'pathauto' => ['title' => 'Pathauto', 'type' => ProjectTypes::MODULE],
    'token' => ['title' => 'Token', 'type' => ProjectTypes::MODULE],
    'webform' => ['title' => 'Webform', 'type' => ProjectTypes::MODULE],
  ];

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private ProjectVersionManager $projectVersionManager,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Creates the given starter projects and imports their release data.
   *
   * Safe to run repeatedly: existing projects are kept and their release
   * data refreshed. A failure for one project is logged and skipped so it
   * does not abort the rest of the seed.
   *
   * @param list<string>|null $shortnames
   *   Starter-project shortnames to seed, or NULL for the whole set.
   *
   * @return list<string>
   *   The shortnames that were seeded successfully.
   */
  public function seed(?array $shortnames = NULL): array {
    $storage = $this->entityTypeManager->getStorage('simplytest_project');
    $seeded = [];
    foreach ($shortnames ?? array_keys(self::STARTER_PROJECTS) as $shortname) {
      $info = self::STARTER_PROJECTS[$shortname] ?? NULL;
      if ($info === NULL) {
        $this->logger->warning('Seeding skipped %project: not part of the starter set.', [
          '%project' => $shortname,
        ]);
        continue;
      }
      try {
        $existing = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('shortname', $shortname)
          ->execute();
        if ($existing === []) {
          // Saving triggers the release-history import via the entity insert
          // hook; updateData() no-ops on the resulting 304 when it runs again
          // for an existing project below.
          $storage->create([
            'title' => $info['title'],
            'shortname' => $shortname,
            'sandbox' => FALSE,
            'type' => $info['type'],
            'creator' => NULL,
            'usage' => 0,
          ])->save();
        }
        else {
          $this->projectVersionManager->updateData($shortname);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Seeding skipped %project: %message', [
          '%project' => $shortname,
          '%message' => $e->getMessage(),
        ]);
        continue;
      }
      $seeded[] = $shortname;
    }
    return $seeded;
  }

}
