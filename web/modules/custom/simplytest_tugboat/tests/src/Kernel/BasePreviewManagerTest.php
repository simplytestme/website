<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_tugboat\BasePreviewManager;

/**
 * Covers how base previews are found, rebuilt, and retired.
 *
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\BasePreviewManager
 */
final class BasePreviewManagerTest extends KernelTestBase {

  protected static $modules = [
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  private BasePreviewManager $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->set('repository_base', 'main')
      ->save();
    $this->sut = $this->container->get('simplytest_tugboat.base_preview_manager');
  }

  /**
   * Every supported core major and every one click demo gets a base.
   *
   * @covers ::names
   */
  public function testNames(): void {
    // Starshot shares the drupal10 base, so it must not appear twice.
    self::assertEquals(
      ['drupal7', 'drupal8', 'drupal9', 'drupal10', 'drupal11', 'umami', 'commerce'],
      $this->sut->names(),
    );
  }

  /**
   * The newest base that can be built on wins.
   *
   * @covers ::findUsable
   */
  public function testFindUsable(): void {
    // Not the one still building, and not the older ones.
    self::assertEquals('base-drupal9-id', $this->sut->findUsable('drupal9'));
    // Not the newer one that failed.
    self::assertEquals('base-drupal10-id', $this->sut->findUsable('drupal10'));
    // A suspended base is still a base.
    self::assertEquals('base-drupal7-id', $this->sut->findUsable('drupal7'));
    // Nothing on Tugboat carries this name.
    self::assertNull($this->sut->findUsable('drupal11'));
    // A sandbox is never a base, whatever its name.
    self::assertNull($this->sut->findUsable('master'));
  }

  /**
   * A rebuild is a fresh preview with generated config and the base name.
   *
   * @covers ::rebuild
   */
  public function testRebuild(): void {
    self::assertEquals('abc123', $this->sut->rebuild('drupal10'));

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    self::assertEquals('kerneltestrepo', $payload['repo']);
    self::assertEquals('main', $payload['ref']);
    self::assertEquals('branch', $payload['type']);
    self::assertEquals('base-drupal10', $payload['name']);
    // A base is built from scratch, never on another base.
    self::assertEquals('none', $payload['base']);
    self::assertEquals('tugboatqa/php:8.2-apache', $payload['config']['services']['php']['image']);
    self::assertArrayHasKey('init', $payload['config']['services']['php']['commands']);
    self::assertArrayNotHasKey('build', $payload['config']['services']['php']['commands']);
  }

  /**
   * One refused base does not stop the rest.
   *
   * @covers ::rebuildAll
   */
  public function testRebuildAllContinuesPastFailures(): void {
    $this->config('tugboat.settings')->set('repository_id', 'brokenrepo')->save();
    $this->sut = $this->container->get('simplytest_tugboat.base_preview_manager');

    $started = $this->sut->rebuildAll();

    self::assertEquals(array_fill_keys($this->sut->names(), NULL), $started);
    $logger = $this->container->get('simplytest_projects_test.logger');
    self::assertTrue($logger->hasMessageContaining('Tugboat refused to build base preview drupal7'));
    self::assertTrue($logger->hasMessageContaining('Tugboat refused to build base preview commerce'));
  }

  /**
   * @covers ::rebuildAll
   */
  public function testRebuildAll(): void {
    $started = $this->sut->rebuildAll();
    self::assertEquals(array_fill_keys($this->sut->names(), 'abc123'), $started);
  }

  /**
   * Only replaced bases nothing builds on, and failed builds, are deleted.
   *
   * @covers ::prune
   */
  public function testPrune(): void {
    $deleted = $this->sut->prune();

    self::assertEquals(['base-drupal9-stale-id', 'base-drupal10-failed-id'], $deleted);
    $requests = $this->container->get('state')->get('tugboat.deleted_previews');
    self::assertEquals(['base-drupal9-stale-id', 'base-drupal10-failed-id'], array_column($requests, 'id'));
    // Tugboat refuses to delete a base preview without this.
    foreach ($requests as $request) {
      self::assertEquals(['force' => TRUE], $request['payload']);
    }
  }

  /**
   * The inventory lists every base, newest first, including missing ones.
   *
   * @covers ::inventory
   */
  public function testInventory(): void {
    $inventory = $this->sut->inventory();

    self::assertEquals($this->sut->names(), array_keys($inventory));
    self::assertEquals(
      ['base-drupal9-building-id', 'base-drupal9-id', 'base-drupal9-stale-id', 'base-drupal9-busy-id'],
      array_column($inventory['drupal9'], 'id'),
    );
    self::assertEquals([], $inventory['drupal11']);
  }

}
