<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Unit\ReleaseHistory;

use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use Drupal\simplytest_projects\ReleaseHistory\Processor;
use Drupal\simplytest_projects\ReleaseHistory\ProjectRelease;

/**
 * Tests processing of release history data
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ReleaseHistory\Processor
 */
final class ProcessorTest extends ReleaseHistoryUnitTestBase {

  /**
   * @covers ::getData
   */
  public function testGetData() {
    $client = $this->getMockedHttpClient();
    $fetcher = new Fetcher($client);
    $data = $fetcher->getProjectData('pathauto', 'current');

    $processed_data = Processor::getData($data);
    self::assertEquals('pathauto', $processed_data['short_name']);
    self::assertEquals('Pathauto', $processed_data['title']);
    self::assertEquals('project_module', $processed_data['type']);
    self::assertCount(18, $processed_data['releases']);

    $release_8x18 = $processed_data['releases']['8.x-1.8'];
    assert($release_8x18 instanceof ProjectRelease);
    self::assertEquals('pathauto 8.x-1.8', $release_8x18->name);
    self::assertEquals('^8.8 || ^9', $release_8x18->core_compatibility);
    self::assertFalse($release_8x18->isInsecure());
    self::assertTrue($release_8x18->isCoreCompatible('8.9.1'));
    self::assertTrue($release_8x18->isCoreCompatible('9.1.3'));
    self::assertFalse($release_8x18->isCoreCompatible('8.7.7'));
  }

  public function testCoreCompatibility() {
    $client = $this->getMockedHttpClient();
    $fetcher = new Fetcher($client);
    $data = $fetcher->getProjectData('pathauto', '7.x');
    $processed_data = Processor::getData($data);
    $release_71x13 = $processed_data['releases']['7.x-1.3'];
    assert($release_71x13 instanceof ProjectRelease);
    self::assertFalse($release_71x13->isCoreCompatible('8.9.1'));
    self::assertFalse($release_71x13->isCoreCompatible('9.1.3'));
    self::assertTrue($release_71x13->isCoreCompatible('7.56'));
  }

}
