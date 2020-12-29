<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_launch\Kernel\TypedData;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_launch\Plugin\DataType\InstanceLaunch;
use Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @group simplytest
 * @group simplytest_launch
 */
final class InstanceLaunchDefinitionTest extends KernelTestBase {

  protected static $modules = [
    'tugboat',
    'simplytest_tugboat',
    'simplytest_projects',
    'simplytest_launch',
  ];

  /**
   * @dataProvider instanceLaunchData
   */
  public function testValidation(array $data, array $expected_violations) {
    $typed_data_manager = $this->container->get('typed_data_manager');
    $data = $typed_data_manager->create(InstanceLaunchDefinition::create(), $data);
    assert($data instanceof InstanceLaunch);
    $constraints = $data->validate();
    $messages = array_map(static function (ConstraintViolationInterface $violation) {
      return sprintf("%s: %s", $violation->getPropertyPath(), $violation->getMessage());
    }, \iterator_to_array($constraints));
    $this->assertCount(count($expected_violations), $constraints, var_export($messages, TRUE));
    $this->assertEquals($messages, $expected_violations);
  }

  public function instanceLaunchData(): \Generator {
    yield [
      [
        'project' => [
          'shortname' => '',
        ],
        'version' => '',
        'drupalVersion' => '',
        'installProfile' => '',
        'manualInstall' => '0',
      ],
      [
        0 => 'project.shortname: This value should not be blank.',
        1 => 'project.type: This value should not be blank.',
        2 => 'project.type: This value should not be null.',
        3 => 'project.sandbox: This value should not be null.',
        4 => 'version: This value should not be blank.',
        5 => 'drupalVersion: This value should not be blank.',
        6 => 'installProfile: This value should not be blank.',
      ]
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
        ],
        'version' => '8.x-1.9',
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      []
    ];
    yield [
      [
        'project' => [
          'shortname' => 't0k?en',
          'type' => 'module',
          'sandbox' => false,
        ],
        'version' => '8.x-1.9',
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      [
        0 => 'project.shortname: This value is not valid.',
      ]
    ];
  }

}
