<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Unit\ReleaseHistory;

use Drupal\Tests\simplytest_projects\Traits\MockedReleaseHttpClientTrait;
use Drupal\Tests\UnitTestCase;

abstract class ReleaseHistoryUnitTestBase extends UnitTestCase {

  use MockedReleaseHttpClientTrait;

}
