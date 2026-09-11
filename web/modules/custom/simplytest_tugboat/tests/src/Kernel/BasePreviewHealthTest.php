<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects_test\BufferedLogger;
use Drupal\simplytest_tugboat\BasePreviewHealth;
use Drupal\simplytest_tugboat\BasePreviewStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the report on how the base previews are holding up.
 */
#[CoversClass(BasePreviewHealth::class)]
#[Group('simplytest')]
#[Group('simplytest_tugboat')]
#[RunTestsInSeparateProcesses]
final class BasePreviewHealthTest extends KernelTestBase {

  /**
   * A lifetime no preview in the mocked repository can outlive.
   */
  private const int TEN_YEARS = 315360000;

  /**
   * A lifetime every preview in the mocked repository has outlived.
   */
  private const int ONE_MINUTE = 60;

  protected static $modules = [
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  private BasePreviewHealth $sut;

  private BufferedLogger $logger;

  protected function setUp(): void {
    parent::setUp();
    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();
    $this->sut = $this->container->get('simplytest_tugboat.base_preview_health');
    $this->logger = $this->container->get('simplytest_projects_test.logger');
  }

  /**
   * Every base is judged, and only the broken ones are logged.
   */
  public function testReportsWhatEachBaseIsDoing(): void {
    $statuses = $this->sut->report(self::TEN_YEARS);

    self::assertEquals([
      // A suspended base is still a base.
      'drupal7' => BasePreviewStatus::Ok,
      // Its only build failed.
      'drupal8' => BasePreviewStatus::Missing,
      // The newest is still building, so the previous one is in use.
      'drupal9' => BasePreviewStatus::Ok,
      // The newest failed, and the previous one carries launches.
      'drupal10' => BasePreviewStatus::Failed,
      // Nothing on Tugboat carries the name.
      'drupal11' => BasePreviewStatus::Missing,
      'commerce' => BasePreviewStatus::Ok,
      'starshot' => BasePreviewStatus::Missing,
      'umami' => BasePreviewStatus::Ok,
    ], $statuses);

    // A one click demo with no base is the case this exists for: a demo that
    // stops building leaves nothing to clone.
    self::assertTrue($this->logger->hasMessageContaining('Base preview starshot has no usable build.'));
    self::assertTrue($this->logger->hasMessageContaining('The latest build of base preview drupal10 failed.'));
    // The message says what launches are running on in the meantime.
    self::assertTrue($this->logger->hasMessageContaining('Launches keep using the base built 2 years'));
    // A healthy base is not worth a word.
    self::assertEquals(0, $this->countMessagesFor('drupal9'));
    self::assertEquals(0, $this->countMessagesFor('umami'));
  }

  /**
   * A base nothing has replaced in two cycles is reported.
   */
  public function testReportsBasesThatStoppedRebuilding(): void {
    $statuses = $this->sut->report(self::ONE_MINUTE);

    self::assertEquals(BasePreviewStatus::Stale, $statuses['umami']);
    self::assertEquals(BasePreviewStatus::Stale, $statuses['drupal7']);
    // A failed rebuild is the more useful thing to say about a base.
    self::assertEquals(BasePreviewStatus::Failed, $statuses['drupal10']);
    self::assertTrue($this->logger->hasMessageContaining('Base preview umami has not been replaced in 2 years'));
  }

  /**
   * A base that stays broken is reported once a cycle, not once a cron run.
   */
  public function testReportsAProblemOncePerLifetime(): void {
    $this->sut->report(self::TEN_YEARS);
    self::assertEquals(1, $this->countMessagesFor('drupal10'));

    $this->sut->report(self::TEN_YEARS);
    self::assertEquals(1, $this->countMessagesFor('drupal10'));

    // Unless what is wrong with it changes. Shortening the lifetime makes
    // every usable base stale, which drupal7 was not a moment ago.
    self::assertEquals(0, $this->countMessagesFor('drupal7'));
    $this->sut->report(self::ONE_MINUTE);
    self::assertEquals(1, $this->countMessagesFor('drupal7'));
    // The failed one has not changed, and is still inside its lifetime.
    self::assertEquals(1, $this->countMessagesFor('drupal10'));
  }

  /**
   * A problem still there a lifetime later is reported again.
   */
  public function testReportsAgainOnTheNextLifetime(): void {
    $this->sut->report(self::ONE_MINUTE);
    self::assertEquals(1, $this->countMessagesFor('starshot'));

    $state = $this->container->get('state');
    $reported = $state->get('simplytest_tugboat.base_preview_health_reported');
    $reported['starshot']['time'] -= self::ONE_MINUTE + 1;
    $state->set('simplytest_tugboat.base_preview_health_reported', $reported);

    $this->sut->report(self::ONE_MINUTE);
    self::assertEquals(2, $this->countMessagesFor('starshot'));
  }

  /**
   * A base that recovers is reported the next time it breaks.
   */
  public function testForgetsABaseThatRecovers(): void {
    $this->sut->report(self::ONE_MINUTE);
    self::assertEquals(1, $this->countMessagesFor('drupal7'));

    // Healthy again, so nothing is held against it.
    $this->sut->report(self::TEN_YEARS);
    self::assertArrayNotHasKey(
      'drupal7',
      $this->container->get('state')->get('simplytest_tugboat.base_preview_health_reported'),
    );

    $this->sut->report(self::ONE_MINUTE);
    self::assertEquals(2, $this->countMessagesFor('drupal7'));
  }

  /**
   * How many log messages name a base.
   */
  private function countMessagesFor(string $name): int {
    $messages = array_filter(
      $this->logger->getMessages(),
      static fn (string $message): bool => str_contains($message, "preview $name "),
    );
    return count($messages);
  }

}
