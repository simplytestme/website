<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Adds indexes for the columns projects are looked up by.
 *
 * Every launch, deep link, and lookup selects a project by exact shortname,
 * and cron selects stale projects by timestamp. Without these the table has
 * only its primary key, so each of those is a full scan.
 */
final class SimplytestProjectStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   *
   * @param bool $reset
   *
   * @return array<string, array<string, mixed>>
   */
  #[\Override]
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $schema[$this->storage->getBaseTable()]['indexes'] += [
      'simplytest_project__shortname' => ['shortname'],
      'simplytest_project__timestamp' => ['timestamp'],
    ];
    return $schema;
  }

}
