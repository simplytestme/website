<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Unit\ReleaseHistory;

use Drupal\simplytest_projects\ReleaseHistory\Fetcher;

/**
 * Tests fetching release data
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ReleaseHistory\Fetcher
 */
final class FetcherTest extends ReleaseHistoryUnitTestBase {

  /**
   * @param string $channel
   * @param bool $expected_exception
   *
   * @dataProvider releaseChannelData
   */
  public function testValidReleaseChannels(string $channel, bool $expected_exception) {
    $sut = new Fetcher($this->getMockedHttpClient());

    if ($expected_exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage("Only the 'current' and '7.x' channel are supported, {$channel} was provided.");
    }
    $sut->getProjectData('pathauto', $channel);
    self::assertTrue(true);
  }

  public function releaseChannelData(): \Generator {
    yield ['current', FALSE];
    yield ['7.x', FALSE];
    yield ['6.x', TRUE];
  }

}
