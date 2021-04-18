<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group simplytest
 */
final class CoreVersionsInstalledTest extends BrowserTestBase {

  protected $profile = 'simplytest';

  public function testCoreVersionsInstalled() {
    $core_version_manager = $this->container->get('simplytest_projects.core_version_manager');
    self::assertNotEmpty($core_version_manager->getVersions(7));
    self::assertNotEmpty($core_version_manager->getVersions(8));
    self::assertNotEmpty($core_version_manager->getVersions(9));
  }

}
