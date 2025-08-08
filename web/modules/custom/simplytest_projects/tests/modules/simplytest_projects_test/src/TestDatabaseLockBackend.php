<?php

declare(strict_types=1);

namespace Drupal\simplytest_projects_test;

use Drupal\Core\Lock\DatabaseLockBackend;

final class TestDatabaseLockBackend extends DatabaseLockBackend {

  public function resetLockId() {
    $this->lockId = NULL;
  }

}
