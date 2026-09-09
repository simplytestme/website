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
      'composer config --global policy.advisories.block false',
      'rm -rf "${DOCROOT}"',
      'cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/recommended-project:^10 stm --no-install',
      'cd "${TUGBOAT_ROOT}/stm" && composer config minimum-stability dev',
      'cd "${TUGBOAT_ROOT}/stm" && composer config prefer-stable true',
      'cd "${TUGBOAT_ROOT}/stm" && composer require --no-install drush/drush',
      'cd "${TUGBOAT_ROOT}/stm" && composer update --no-ansi',
      'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
    ], $init);
  }

  /**
   * Each release line's base holds a project at its newest release.
   *
   * @covers ::basePreview
   */
  public function testProjectPerReleaseLine(): void {
    $init = static fn (array $config): string => implode("\n", $config['services']['php']['commands']['init']);

    // Drupal 7 and 8 sandboxes are git checkouts, so there is no project.
    self::assertStringNotContainsString('create-project', $init($this->sut->basePreview('drupal7')));
    self::assertStringNotContainsString('create-project', $init($this->sut->basePreview('drupal8')));

    self::assertStringContainsString('drupal/recommended-project:^9 stm', $init($this->sut->basePreview('drupal9')));
    self::assertStringContainsString('drupal/recommended-project:^11 stm', $init($this->sut->basePreview('drupal11')));
    // A demo installs whatever core is current, so its base only warms the
    // Composer cache.
    self::assertStringContainsString('drupal/recommended-project /tmp', $init($this->sut->basePreview('umami')));
    self::assertStringNotContainsString(' stm', $init($this->sut->basePreview('umami')));
  }

  /**
   * A sandbox reuses the base's project when it holds the requested release.
   *
   * @covers ::generate
   */
  public function testSandboxReusesBaseProject(): void {
    $build = $this->sut->generate([
      'perform_install' => TRUE,
      'install_profile' => 'standard',
      'drupal_core_version' => '11.2.2',
      'project_type' => 'Module',
      'project_version' => '1.15.0',
      'project' => 'token',
      'patches' => [],
      'additionals' => [],
      'instance_id' => 'x',
      'hash' => 'y',
      'major_version' => 11,
    ])['services']['php']['commands']['build'];
    $setup = implode("\n", $build);

    // The installed release decides, and only an exact match reuses it.
    self::assertStringContainsString('composer show drupal/core --format=json', $setup);
    self::assertStringContainsString('= "11.2.2" ] && echo "Reusing Drupal 11.2.2 from the base preview" || (', $setup);
    // The fallback clears whatever the base left, or create-project refuses.
    self::assertStringContainsString('rm -rf "${DOCROOT}" "${TUGBOAT_ROOT}/stm" && cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/recommended-project:11.2.2 stm', $setup);
    // Core is pinned on both paths, or composer update could move it.
    self::assertContains('cd "${TUGBOAT_ROOT}/stm" && composer require --dev --no-install drupal/core:11.2.2', $build);
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
