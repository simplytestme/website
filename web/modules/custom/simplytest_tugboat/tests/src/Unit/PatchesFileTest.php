<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_tugboat\PreviewConfigGenerator;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the patches.json handed to composer-patches.
 *
 * @group simplytest
 * @group simplytest_tugboat
 */
final class PatchesFileTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Merge request patches are fetched as a squashed diff.
   *
   * @see https://www.drupal.org/project/simplytest/issues/3588836
   */
  public function testMergeRequestPatchUrlBecomesDiff(): void {
    $patches = $this->getPatchesFile([
      'project_type' => ProjectTypes::CORE,
      'project' => 'drupal',
      'project_version' => '11.x-dev',
      'patches' => ['https://git.drupalcode.org/project/drupal/-/merge_requests/5876.patch'],
    ]);

    self::assertSame(
      'https://git.drupalcode.org/project/drupal/-/merge_requests/5876.diff',
      $patches['drupal/core'][0]['url']
    );
    self::assertSame('STM patch 5876.diff', $patches['drupal/core'][0]['description']);
  }

  /**
   * A query string survives the rewrite, so cache busting still works.
   */
  public function testMergeRequestPatchUrlKeepsQueryString(): void {
    $patches = $this->getPatchesFile([
      'patches' => ['https://git.drupalcode.org/project/token/-/merge_requests/12.patch?ref_type=heads'],
    ]);

    self::assertSame(
      'https://git.drupalcode.org/project/token/-/merge_requests/12.diff?ref_type=heads',
      $patches['drupal/token'][0]['url']
    );
  }

  /**
   * A plain patch file is left alone.
   */
  public function testFilePatchUrlIsUnchanged(): void {
    $url = 'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch';
    $patches = $this->getPatchesFile(['patches' => [$url]]);

    self::assertSame($url, $patches['drupal/token'][0]['url']);
  }

  /**
   * Core patches are cut from the monorepo, so they carry the core/ prefix.
   */
  public function testCoreUsesDepthTwo(): void {
    $patches = $this->getPatchesFile([
      'project_type' => ProjectTypes::CORE,
      'project' => 'drupal',
      'project_version' => '11.x-dev',
      'patches' => ['https://www.drupal.org/files/issues/3185080-3.patch'],
    ]);

    self::assertSame(2, $patches['drupal/core'][0]['depth']);
  }

  /**
   * Contrib repositories are flat, so their patches apply at the root.
   */
  public function testContribUsesDepthOne(): void {
    $patches = $this->getPatchesFile([
      'patches' => ['https://www.drupal.org/files/issues/3185080-3.patch'],
    ]);

    self::assertSame(1, $patches['drupal/token'][0]['depth']);
  }

  /**
   * Every patch gets the GNU patch fallback used when git apply refuses.
   */
  public function testPatchesCarryTheFreeformFallback(): void {
    $patches = $this->getPatchesFile([
      'patches' => ['https://www.drupal.org/files/issues/3185080-3.patch'],
    ]);

    self::assertSame('patch', $patches['drupal/token'][0]['extra']['freeform']['executable']);
    self::assertStringContainsString(
      '--dry-run',
      $patches['drupal/token'][0]['extra']['freeform']['dry_run_args']
    );
  }

  /**
   * Patches on additional projects are keyed by their own package.
   */
  public function testAdditionalProjectPatches(): void {
    $patches = $this->getPatchesFile([
      'patches' => ['https://www.drupal.org/files/issues/3185080-3.patch'],
      'additionals' => [
        [
          'shortname' => 'pathauto',
          'version' => '8.x-1.8',
          'patches' => [
            'https://www.drupal.org/files/issues/one.patch',
            'https://www.drupal.org/files/issues/two.patch',
          ],
        ],
      ],
    ]);

    self::assertSame(['drupal/token', 'drupal/pathauto'], array_keys($patches));
    self::assertCount(2, $patches['drupal/pathauto']);
    self::assertSame('STM patch two.patch', $patches['drupal/pathauto'][1]['description']);
  }

  /**
   * Two patches sharing a filename both survive.
   *
   * The previous `composer patch-add` build aborted here, because it rejected
   * any patch whose description was already taken.
   */
  public function testPatchesWithTheSameFilename(): void {
    $patches = $this->getPatchesFile([
      'patches' => [
        'https://www.drupal.org/files/issues/2020-01-01/1234-5.patch',
        'https://www.drupal.org/files/issues/2021-01-01/1234-5.patch',
      ],
    ]);

    self::assertCount(2, $patches['drupal/token']);
  }

  /**
   * The empty patch row the form always submits is not a patch.
   *
   * Reproduces the report: a project plus two additional projects, none of them
   * patched. composer-patches takes the empty URL at face value and fails the
   * whole update with "$url must not be an empty string".
   *
   * @see https://www.drupal.org/project/simplytest/issues/3348026
   */
  public function testEmptyPatchRowsAreIgnored(): void {
    $commands = $this->getBuildCommands([
      'project' => 'antibot',
      'patches' => [''],
      'additionals' => [
        ['shortname' => 'gin', 'version' => '8.x-3.0', 'patches' => ['']],
        ['shortname' => 'gin_login', 'version' => '8.x-2.1', 'patches' => ['']],
      ],
    ]);

    self::assertContains('cd stm && composer update --no-ansi', $commands);
    self::assertSame([], array_filter(
      $commands,
      static fn (string $command): bool => str_contains($command, 'patches.json')
    ));
  }

  /**
   * An empty row alongside a real patch drops only the empty row.
   */
  public function testEmptyRowsAreDroppedFromRealPatches(): void {
    $patches = $this->getPatchesFile([
      'patches' => [''],
      'additionals' => [
        [
          'shortname' => 'pathauto',
          'version' => '8.x-1.8',
          'patches' => ['', 'https://www.drupal.org/files/issues/one.patch', ''],
        ],
      ],
    ]);

    self::assertSame(['drupal/pathauto'], array_keys($patches));
    self::assertCount(1, $patches['drupal/pathauto']);
  }

  /**
   * A field holding only whitespace is empty.
   */
  public function testWhitespaceOnlyPatchIsIgnored(): void {
    $commands = $this->getBuildCommands(['patches' => ['   ']]);

    self::assertSame([], array_filter(
      $commands,
      static fn (string $command): bool => str_contains($command, 'patches.json')
    ));
  }

  /**
   * A build without patches does not install the patcher or go verbose.
   */
  public function testBuildWithoutPatches(): void {
    $commands = $this->getBuildCommands([]);

    self::assertContains('cd stm && composer update --no-ansi', $commands);
    self::assertSame([], array_filter(
      $commands,
      static fn (string $command): bool => str_contains($command, 'composer-patches')
    ));
  }

  /**
   * Reads the patches file out of the generated build commands.
   *
   * @param array<mixed> $overrides
   *   Preview config parameters to override.
   *
   * @return array<string, list<array<string, mixed>>>
   *   The decoded patches, keyed by package name.
   */
  private function getPatchesFile(array $overrides): array {
    $commands = $this->getBuildCommands($overrides);
    $written = array_values(array_filter(
      $commands,
      static fn (string $command): bool => str_ends_with($command, '> patches.json')
    ));
    self::assertCount(1, $written, 'The build writes exactly one patches file.');

    self::assertSame(1, preg_match("/echo '(.*)' > patches\.json$/", $written[0], $matches));
    return json_decode($matches[1], TRUE, 512, JSON_THROW_ON_ERROR)['patches'];
  }

  /**
   * Generates a preview config and returns its build commands.
   *
   * @param array<mixed> $overrides
   *   Preview config parameters to override.
   *
   * @return list<string>
   *   The build commands.
   */
  private function getBuildCommands(array $overrides): array {
    $generator = new PreviewConfigGenerator(
      $this->prophesize(OneClickDemoPluginManager::class)->reveal()
    );
    $config = $generator->generate($overrides + [
      'perform_install' => TRUE,
      'install_profile' => 'standard',
      'drupal_core_version' => '11.1.0',
      'project_type' => ProjectTypes::MODULE,
      'project_version' => '8.x-1.9',
      'project' => 'token',
      'patches' => [],
      'additionals' => [],
      'instance_id' => 'test',
      'hash' => 'test',
      'major_version' => 11,
    ]);
    return $config['services']['php']['commands']['build'];
  }

}
