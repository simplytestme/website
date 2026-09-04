<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\simplytest_tugboat\LaunchRecord;
use Drupal\simplytest_tugboat\LaunchRecorder;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * A broken analytics write must not break launching.
 *
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\LaunchRecorder
 */
final class LaunchRecorderErrorTest extends UnitTestCase {

  /**
   * Nothing was written, so nothing rendered from the table is stale either.
   *
   * @covers ::recordLaunch
   * @covers ::write
   */
  public function testInsertFailureIsLoggedNotThrown(): void {
    $database = $this->createMock(Connection::class);
    $database->method('insert')
      ->with(LaunchRecorder::TABLE_NAME)
      ->willThrowException(new \RuntimeException('the table is gone'));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1756771200);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'Could not record the launch of @project: @message',
        [
          '@project' => 'token',
          '@message' => 'the table is gone',
        ]
      );

    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects($this->never())->method('invalidateTags');

    $recorder = new LaunchRecorder($database, $time, $logger, $invalidator);

    $recorder->recordLaunch(
      LaunchRecord::fromPreviewParameters([
        'perform_install' => TRUE,
        'install_profile' => 'standard',
        'drupal_core_version' => '10.3.0',
        'project_type' => 'Module',
        'project_version' => '8.x-1.9',
        'project' => 'token',
        'patches' => [],
        'additionals' => [],
      ]),
      'preview-abc'
    );
  }

  /**
   * A failed one click demo is named by its plugin ID in the log.
   *
   * @covers ::recordFailure
   * @covers ::write
   */
  public function testOneClickDemoFailureIsLoggedByPluginId(): void {
    $database = $this->createMock(Connection::class);
    $database->method('insert')
      ->with(LaunchRecorder::TABLE_NAME)
      ->willThrowException(new \RuntimeException('nope'));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1756771200);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with(
        'Could not record the launch of @project: @message',
        ['@project' => 'umami', '@message' => 'nope']
      );

    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $recorder = new LaunchRecorder($database, $time, $logger, $invalidator);
    $recorder->recordFailure(LaunchRecord::forOneClickDemo('umami'));
  }

}
