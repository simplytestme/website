<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_projects\Unit\ReleaseHistory;

use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\State\State;
use Drupal\simplytest_projects\Exception\ReleaseHistoryNotModifiedException;
use Drupal\simplytest_projects\ReleaseHistory\Fetcher;
use GuzzleHttp\Client;

/**
 * Tests fetching release data
 *
 * @coversDefaultClass \Drupal\simplytest_projects\ReleaseHistory\Fetcher
 */
final class FetcherTest extends ReleaseHistoryUnitTestBase {

  public function testLastModified() {
    $sut = new Fetcher(new Client(), new State(new KeyValueMemoryFactory()));
    $sut->getProjectData('token', 'current');
    $sut->getProjectData('token', '7.x');
    $this->expectException(ReleaseHistoryNotModifiedException::class);
    $sut->getProjectData('token', 'current');
  }

  /**
   * @param string $channel
   * @param bool $expected_exception
   *
   * @dataProvider releaseChannelData
   */
  public function testValidReleaseChannels(string $channel, bool $expected_exception) {
    $state = new State(new KeyValueMemoryFactory());
    $sut = new Fetcher($this->getMockedHttpClient(), $state);

    if ($expected_exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage("Only the 'current' and '7.x' channel are supported, {$channel} was provided.");
    }
    $sut->getProjectData('pathauto', $channel);
    self::assertNotNull($state->get("release_history_last_modified:pathauto:$channel"));

    $this->expectException(ReleaseHistoryNotModifiedException::class);
    $sut->getProjectData('pathauto', $channel);
  }

  public function releaseChannelData(): \Generator {
    yield ['current', FALSE];
    yield ['7.x', FALSE];
    yield ['6.x', TRUE];
  }

}
