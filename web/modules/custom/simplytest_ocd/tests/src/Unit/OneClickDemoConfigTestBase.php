<?php

namespace Drupal\Tests\simplytest_ocd\Unit;

use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_tugboat\PreviewConfigGenerator;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Base test class for testing Tugboat configuration generation.
 */
abstract class OneClickDemoConfigTestBase extends UnitTestCase {

  use ProphecyTrait;

  protected static $pluginId;
  protected static $pluginClass;

  /**
   * The preview config generator.
   *
   * @var \Drupal\simplytest_tugboat\PreviewConfigGenerator
   */
  protected $previewConfigGenerator;

  protected function setUp(): void {
    parent::setUp();
    $manager = $this->prophesize(OneClickDemoPluginManager::class);
    $manager->createInstance(static::$pluginId)->willReturn(new static::$pluginClass([], static::$pluginId, []));
    $this->previewConfigGenerator = new PreviewConfigGenerator(
      $manager->reveal()
    );
  }

  /**
   * @param string $demo_id
   * @param array $parameters
   * @param array $expected_config
   */
  public function testConfigData() {
    $generated_config = $this->previewConfigGenerator->oneClickDemo(static::$pluginId, []);
    self::assertEquals([
      // Services is the root property, so we do not require test classes to
      // provide it for sample data.
      'services' => $this->getExpectedConfig(),
    ], $generated_config);
  }

  abstract protected function getExpectedConfig(): array;

}
