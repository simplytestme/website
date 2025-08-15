<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects_test;

use Drupal\Core\Lock\DatabaseLockBackend;

/**
 * Test lock backend that changes lock ID to test acquire/release behavior.
 */
final class TestDatabaseLockBackend extends DatabaseLockBackend {

  public function resetLockId(): void {
    $this->lockId = '';
  }

  #[\Override]
  public function getLockId(): string {
    if ($this->lockId === '') {
      return $this->lockId = uniqid((string) mt_rand(), TRUE);
    }
    return parent::getLockId();
  }

}
