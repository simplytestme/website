<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\simplytest_tugboat\LaunchRecord;
use Drupal\simplytest_tugboat\LaunchRecorder;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
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

  use ProphecyTrait;

  /**
   * @covers ::recordLaunch
   * @covers ::write
   */
  public function testInsertFailureIsLoggedNotThrown(): void {
    $database = $this->prophesize(Connection::class);
    $database->insert(LaunchRecorder::TABLE_NAME)
      ->willThrow(new \RuntimeException('the table is gone'));

    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn(1756771200);

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(
      'Could not record the launch of @project: @message',
      [
        '@project' => 'token',
        '@message' => 'the table is gone',
      ]
    )->shouldBeCalledOnce();

    $recorder = new LaunchRecorder(
      $database->reveal(),
      $time->reveal(),
      $logger->reveal()
    );

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
    $database = $this->prophesize(Connection::class);
    $database->insert(LaunchRecorder::TABLE_NAME)
      ->willThrow(new \RuntimeException('nope'));

    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn(1756771200);

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(
      'Could not record the launch of @project: @message',
      ['@project' => 'umami', '@message' => 'nope']
    )->shouldBeCalledOnce();

    $recorder = new LaunchRecorder($database->reveal(), $time->reveal(), $logger->reveal());
    $recorder->recordFailure(LaunchRecord::forOneClickDemo('umami'));
  }

}
