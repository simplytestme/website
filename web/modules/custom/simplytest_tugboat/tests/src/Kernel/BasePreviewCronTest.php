<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_tugboat\Hook\BasePreviewCron;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the cron run that keeps base previews fresh.
 */
#[Group('simplytest')]
#[Group('simplytest_tugboat')]
#[RunTestsInSeparateProcesses]
final class BasePreviewCronTest extends KernelTestBase {

  private const string CREATE_URL = 'https://api.tugboatqa.com/v3/previews';

  protected static $modules = [
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();
  }

  protected function tearDown(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE');
    parent::tearDown();
  }

  /**
   * Only production touches the base previews.
   *
   * The Tugboat token reaches every Lagoon environment, and site install runs
   * cron, so anything else would start a set of builds per PR environment.
   */
  public function testCronSkipsNonProductionEnvironments(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=development');
    $this->cron();
    $this->assertNothingHappened();

    // A local site, with no Lagoon environment at all.
    putenv('LAGOON_ENVIRONMENT_TYPE');
    $this->cron();
    $this->assertNothingHappened();
  }

  /**
   * The first production run prunes and rebuilds; the next one only prunes.
   */
  public function testCronRebuildsOncePerLifetime(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=production');
    $state = $this->container->get('state');

    $this->cron();

    self::assertNotEmpty($state->get('tugboat.deleted_previews'));
    // The last name is the last create request, so this proves the loop ran
    // to the end.
    self::assertEquals('base-umami', $state->get(self::CREATE_URL)['name']);
    self::assertEquals(
      $this->container->get('datetime.time')->getRequestTime(),
      $state->get(BasePreviewCron::REBUILT),
    );

    $state->delete(self::CREATE_URL);
    $state->delete('tugboat.deleted_previews');
    $this->cron();

    self::assertNotEmpty($state->get('tugboat.deleted_previews'));
    self::assertNull($state->get(self::CREATE_URL));
  }

  /**
   * A set older than its lifetime is rebuilt again.
   */
  public function testCronRebuildsAfterLifetime(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=production');
    $state = $this->container->get('state');
    $now = $this->container->get('datetime.time')->getRequestTime();
    $state->set(BasePreviewCron::REBUILT, $now - BasePreviewCron::LIFETIME - 1);

    $this->cron();

    self::assertEquals('base-umami', $state->get(self::CREATE_URL)['name']);
    self::assertEquals($now, $state->get(BasePreviewCron::REBUILT));
  }

  /**
   * Every run reports on the bases, whether or not it rebuilds them.
   */
  public function testCronReportsBasePreviewHealth(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=production');
    $logger = $this->container->get('simplytest_projects_test.logger');

    $this->cron();

    self::assertTrue($logger->hasMessageContaining('Base preview starshot has no usable build.'));
    // Reported before the pruner deleted the failed build it is about.
    self::assertTrue($logger->hasMessageContaining('The latest build of base preview drupal10 failed.'));
  }

  /**
   * The health report and the pruner share one read of the preview list.
   *
   * Tugboat returns the whole repository and has no way to ask for less, so
   * this is the slowest call the site makes and the one that times out.
   */
  public function testCronReadsThePreviewListOnce(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=production');

    $this->cron();

    self::assertEquals(1, $this->container->get('state')->get('tugboat.preview_list_requests'));
  }

  /**
   * A Tugboat that does not answer leaves the bases where they are.
   *
   * The preview list is the whole repository, Tugboat offers no way to ask for
   * less of it, and the request times out often enough that an exception out of
   * cron is noise rather than news.
   */
  public function testCronSurvivesTugboatTimingOut(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=production');
    $this->config('tugboat.settings')->set('repository_id', 'timeoutrepo')->save();

    $this->cron();

    $logger = $this->container->get('simplytest_projects_test.logger');
    self::assertTrue($logger->hasMessageContaining('Tugboat did not answer in time.'));
    // No build is started against a Tugboat that is not answering, and the
    // next run picks the work back up.
    $this->assertNothingHappened();
  }

  /**
   * The Tugboat module's own cron is off.
   *
   * It deletes every preview past the sandbox lifetime that Tugboat does not
   * report as an anchor, which is every base preview here.
   */
  public function testContribCronIsRemoved(): void {
    $modules = [];
    $this->container->get('module_handler')->invokeAllWith(
      'cron',
      static function (callable $hook, string $module) use (&$modules): void {
        $modules[] = $module;
      },
    );

    self::assertContains('simplytest_tugboat', $modules);
    self::assertNotContains('tugboat', $modules);
  }

  private function cron(): void {
    $this->container->get(BasePreviewCron::class)->cron();
  }

  private function assertNothingHappened(): void {
    $state = $this->container->get('state');
    self::assertNull($state->get(self::CREATE_URL));
    self::assertNull($state->get('tugboat.deleted_previews'));
    self::assertNull($state->get(BasePreviewCron::REBUILT));
    self::assertNull($state->get('simplytest_tugboat.base_preview_health_reported'));
  }

}
