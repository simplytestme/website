<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_tugboat\PreviewConfigGenerator;
use Drupal\Tests\UnitTestCase;

/**
 * Covers the config a base preview is built from.
 *
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\PreviewConfigGenerator
 */
final class BasePreviewConfigTest extends UnitTestCase {

  private PreviewConfigGenerator $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->sut = new PreviewConfigGenerator($this->createMock(OneClickDemoPluginManager::class));
  }

  /**
   * A base only has an init stage, on the images its sandboxes use.
   *
   * @covers ::basePreview
   * @dataProvider baseImages
   */
  public function testImagesMatchTheSandboxConfig(string $name, string $php, string $mysql): void {
    $config = $this->sut->basePreview($name);

    self::assertEquals($php, $config['services']['php']['image']);
    self::assertEquals($mysql, $config['services']['mysql']['image']);
    self::assertTrue($config['services']['php']['default']);
    self::assertEquals('mysql', $config['services']['php']['depends']);
    self::assertEquals(['init'], array_keys($config['services']['php']['commands']));
  }

  /**
   * @return \Generator<string, array{string, string, string}>
   */
  public static function baseImages(): \Generator {
    // These have to stay in step with the sandbox config for the same major,
    // which the Drupal*ConfigTest classes pin down.
    yield 'drupal7' => ['drupal7', 'tugboatqa/php:7.4-apache', 'tugboatqa/mysql:5'];
    yield 'drupal8' => ['drupal8', 'tugboatqa/php:7.4-apache', 'tugboatqa/mysql:5'];
    yield 'drupal9' => ['drupal9', 'tugboatqa/php:8.1-apache', 'tugboatqa/mysql:5'];
    yield 'drupal10' => ['drupal10', 'tugboatqa/php:8.2-apache', 'tugboatqa/mysql:5'];
    yield 'drupal11' => ['drupal11', 'tugboatqa/php:apache', 'tugboatqa/mysql:8'];
    yield 'umami' => ['umami', 'tugboatqa/php:8.3-apache', 'tugboatqa/mysql:8'];
    yield 'commerce' => ['commerce', 'tugboatqa/php:8.3-apache', 'tugboatqa/mysql:8'];
  }

  /**
   * The init stage carries what every sandbox build used to repeat.
   *
   * @covers ::basePreview
   */
  public function testInitCommands(): void {
    $init = $this->sut->basePreview('drupal10')['services']['php']['commands']['init'];

    self::assertEquals([
      'docker-php-ext-install bcmath',
      'a2enmod headers rewrite',
      'wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq',
      'composer self-update',
      'composer -n create-project drupal/recommended-project:^10 /tmp/warm-cache && cd /tmp/warm-cache && composer -n require drush/drush && rm -rf /tmp/warm-cache',
    ], $init);
  }

  /**
   * The Composer cache is warmed for the release line the base serves.
   *
   * @covers ::basePreview
   */
  public function testComposerCacheWarming(): void {
    $warm = static fn (array $config): string => implode("\n", $config['services']['php']['commands']['init']);

    // Drupal 7 and 8 sandboxes are git checkouts, so there is nothing to warm.
    self::assertStringNotContainsString('create-project', $warm($this->sut->basePreview('drupal7')));
    self::assertStringNotContainsString('create-project', $warm($this->sut->basePreview('drupal8')));

    self::assertStringContainsString('drupal/recommended-project:^9 ', $warm($this->sut->basePreview('drupal9')));
    self::assertStringContainsString('drupal/recommended-project:^11 ', $warm($this->sut->basePreview('drupal11')));
    // A demo installs whatever core is current.
    self::assertStringContainsString('drupal/recommended-project /tmp', $warm($this->sut->basePreview('umami')));
  }

  /**
   * @covers ::majorVersionFromBaseName
   */
  public function testMajorVersionFromBaseName(): void {
    self::assertEquals(10, PreviewConfigGenerator::majorVersionFromBaseName('drupal10'));
    self::assertEquals(7, PreviewConfigGenerator::majorVersionFromBaseName('drupal7'));
    self::assertNull(PreviewConfigGenerator::majorVersionFromBaseName('umami'));
    self::assertNull(PreviewConfigGenerator::majorVersionFromBaseName('drupal10-old'));
  }

}
