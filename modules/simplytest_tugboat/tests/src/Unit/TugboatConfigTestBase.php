<?php

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Core\Render\RendererInterface;
use Drupal\simplytest_tugboat\PreviewConfigGenerator;
use Drupal\Tests\UnitTestCase;

/**
 * Base test class for testing Tugboat configuration generation.
 */
abstract class TugboatConfigTestBase extends UnitTestCase {

  /**
   * The preview config generator.
   *
   * @var \Drupal\simplytest_tugboat\PreviewConfigGenerator
   */
  protected $previewConfigGenerator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'simplytest_projects',
    'tugboat',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->previewConfigGenerator = new PreviewConfigGenerator(
      $this->prophesize(RendererInterface::class)->reveal()
    );
  }

  /**
   * @param array $parameters
   * @param array $expected_config
   *
   * @dataProvider configData
   */
  public function testConfigData(array $parameters, array $expected_config) {
    $generated_config = $this->previewConfigGenerator->generate($parameters);
    self::assertEquals([
      // Services is the root property, so we do not require test classes to
      // provide it for sample data.
      'services' => $expected_config
    ], $generated_config);
  }

  /**
   * The test data.
   *
   * @return \Generator
   */
  abstract public function configData(): \Generator;

}
