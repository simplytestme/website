<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\SiteTemplateInfo;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The curated list is somebody else's data, so nothing in it is trusted.
 */
#[CoversClass(SiteTemplateInfo::class)]
final class SiteTemplateInfoTest extends UnitTestCase {

  public function testReadsAFullEntry(): void {
    $template = SiteTemplateInfo::fromCuratedEntry('byte', [
      'name' => ' Byte ',
      'description' => ' A SaaS product website. ',
      'screenshot' => 'https://git.drupalcode.org/project/byte/-/raw/1.x/screenshot.webp',
      'package' => 'drupal/byte',
      'links' => [
        ['text' => 'Demo', 'url' => 'https://example.tugboatqa.com/'],
        ['url' => 'https://new.drupal.org/site-template/byte'],
      ],
      'creator' => 'Drupal CMS',
    ]);

    self::assertNotNull($template);
    self::assertSame('byte', $template->machineName);
    self::assertSame('Byte', $template->name);
    self::assertSame('A SaaS product website.', $template->description);
    self::assertSame('drupal/byte', $template->package);
    self::assertSame('byte', $template->recipe);
    self::assertSame('Drupal CMS', $template->creator);
    self::assertSame([
      ['text' => 'Demo', 'url' => 'https://example.tugboatqa.com/'],
      // No text, so the label falls back.
      ['text' => 'Learn more', 'url' => 'https://new.drupal.org/site-template/byte'],
    ], $template->links);
  }

  /**
   * The recipe directory is the package name, which need not match the key.
   */
  public function testRecipeComesFromThePackageName(): void {
    $template = SiteTemplateInfo::fromCuratedEntry('nuxt', [
      'name' => 'Nuxt Starter',
      'package' => 'drupal/lupus_decoupled_starter',
    ]);

    self::assertNotNull($template);
    self::assertSame('nuxt', $template->machineName);
    self::assertSame('lupus_decoupled_starter', $template->recipe);
  }

  /**
   * An entry's key can stand in for a link's label.
   */
  public function testLinkKeyLabelsTheLink(): void {
    $template = SiteTemplateInfo::fromCuratedEntry('nuxt', [
      'name' => 'Nuxt Starter',
      'package' => 'drupal/lupus_decoupled_starter',
      'links' => ['demo' => 'https://example.surge.sh', 'docs' => 'https://example.org/guide'],
    ]);

    self::assertNotNull($template);
    self::assertSame([
      ['text' => 'Demo', 'url' => 'https://example.surge.sh'],
      ['text' => 'Learn more', 'url' => 'https://example.org/guide'],
    ], $template->links);
  }

  public function testOptionalKeysMayBeAbsent(): void {
    $template = SiteTemplateInfo::fromCuratedEntry('forma', [
      'name' => 'Forma',
      'package' => 'drupal/forma',
    ]);

    self::assertNotNull($template);
    self::assertSame('', $template->description);
    self::assertNull($template->screenshot);
    self::assertNull($template->creator);
    self::assertSame([], $template->links);
  }

  /**
   * @param array<string, mixed>|string $values
   */
  #[DataProvider('unusableEntries')]
  public function testUnusableEntriesAreRejected(array|string $values): void {
    self::assertNull(SiteTemplateInfo::fromCuratedEntry('thing', $values));
  }

  /**
   * The key becomes half of a plugin ID, so it has to be a machine name.
   */
  #[DataProvider('badMachineNames')]
  public function testBadKeysAreRejected(string $machine_name): void {
    self::assertNull(SiteTemplateInfo::fromCuratedEntry($machine_name, [
      'name' => 'Thing',
      'package' => 'drupal/thing',
    ]));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function badMachineNames(): iterable {
    // The plugin system splits derivative IDs on a colon.
    yield 'colon' => ['site_template:byte'];
    yield 'dash' => ['site-template'];
    yield 'uppercase' => ['Byte'];
    yield 'space' => ['two words'];
    yield 'empty' => [''];
    yield 'path traversal' => ['../../etc/passwd'];
  }

  /**
   * @return iterable<string, array{array<string, mixed>|string}>
   */
  public static function unusableEntries(): iterable {
    yield 'not an array' => ['just a string'];
    yield 'no name' => [['package' => 'drupal/thing']];
    yield 'empty name' => [['name' => '   ', 'package' => 'drupal/thing']];
    yield 'no package' => [['name' => 'Thing']];
    yield 'package is not vendor/name' => [['name' => 'Thing', 'package' => 'not-a-package']];
    yield 'package name is not a string' => [['name' => 'Thing', 'package' => ['repository' => NULL]]];
    // Needs a license key, which a sandbox cannot supply.
    yield 'paid' => [[
      'name' => 'Thing',
      'package' => 'drupal/thing',
      'purchase' => ['price' => 899, 'url' => 'https://example.com'],
    ]];
    // Needs Composer configured with another repository.
    yield 'alternate repository' => [[
      'name' => 'Thing',
      'package' => ['name' => 'vendor/thing', 'repository' => 'https://packages.example.com'],
    ]];
  }

  /**
   * A screenshot has to be an absolute HTTPS URL to be rendered.
   */
  #[DataProvider('badScreenshots')]
  public function testBadScreenshotsAreDropped(mixed $screenshot): void {
    $template = SiteTemplateInfo::fromCuratedEntry('byte', [
      'name' => 'Byte',
      'package' => 'drupal/byte',
      'screenshot' => $screenshot,
    ]);

    self::assertNotNull($template);
    self::assertNull($template->screenshot);
  }

  /**
   * @return iterable<string, array{mixed}>
   */
  public static function badScreenshots(): iterable {
    yield 'relative' => ['/sites/default/files/shot.webp'];
    yield 'plain http' => ['http://example.com/shot.webp'];
    yield 'javascript' => ['javascript:alert(1)'];
    yield 'not a string' => [['https://example.com/shot.webp']];
  }

  /**
   * A link that is not an absolute external URL is dropped, not rendered.
   */
  public function testBadLinksAreDropped(): void {
    $template = SiteTemplateInfo::fromCuratedEntry('byte', [
      'name' => 'Byte',
      'package' => 'drupal/byte',
      'links' => [
        ['text' => 'Fine', 'url' => 'https://example.com/ok'],
        ['text' => 'Internal', 'url' => '/admin/people'],
        ['text' => 'Script', 'url' => 'javascript:alert(1)'],
        ['text' => 'Nothing'],
      ],
    ]);

    self::assertNotNull($template);
    self::assertSame([['text' => 'Fine', 'url' => 'https://example.com/ok']], $template->links);
  }

  public function testSurvivesStorage(): void {
    $template = SiteTemplateInfo::fromCuratedEntry('byte', [
      'name' => 'Byte',
      'description' => 'A SaaS product website.',
      'screenshot' => 'https://example.com/shot.webp',
      'package' => 'drupal/byte',
      'links' => [['text' => 'Demo', 'url' => 'https://example.com/demo']],
      'creator' => 'Drupal CMS',
    ]);

    self::assertNotNull($template);
    self::assertEquals($template, SiteTemplateInfo::fromArray($template->toArray()));
  }

}
