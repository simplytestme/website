<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Covers the cron run that keeps base previews fresh.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
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
    simplytest_tugboat_cron();
    $this->assertNothingHappened();

    // A local site, with no Lagoon environment at all.
    putenv('LAGOON_ENVIRONMENT_TYPE');
    simplytest_tugboat_cron();
    $this->assertNothingHappened();
  }

  /**
   * The first production run prunes and rebuilds; the next one only prunes.
   */
  public function testCronRebuildsOncePerLifetime(): void {
    putenv('LAGOON_ENVIRONMENT_TYPE=production');
    $state = $this->container->get('state');

    simplytest_tugboat_cron();

    self::assertNotEmpty($state->get('tugboat.deleted_previews'));
    // The last name is the last create request, so this proves the loop ran
    // to the end.
    self::assertEquals('base-umami', $state->get(self::CREATE_URL)['name']);
    self::assertEquals(
      $this->container->get('datetime.time')->getRequestTime(),
      $state->get(SIMPLYTEST_TUGBOAT_BASE_PREVIEWS_REBUILT),
    );

    $state->delete(self::CREATE_URL);
    $state->delete('tugboat.deleted_previews');
    simplytest_tugboat_cron();

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
    $state->set(SIMPLYTEST_TUGBOAT_BASE_PREVIEWS_REBUILT, $now - SIMPLYTEST_TUGBOAT_BASE_PREVIEW_LIFETIME - 1);

    simplytest_tugboat_cron();

    self::assertEquals('base-umami', $state->get(self::CREATE_URL)['name']);
    self::assertEquals($now, $state->get(SIMPLYTEST_TUGBOAT_BASE_PREVIEWS_REBUILT));
  }

  private function assertNothingHappened(): void {
    $state = $this->container->get('state');
    self::assertNull($state->get(self::CREATE_URL));
    self::assertNull($state->get('tugboat.deleted_previews'));
    self::assertNull($state->get(SIMPLYTEST_TUGBOAT_BASE_PREVIEWS_REBUILT));
  }

}
