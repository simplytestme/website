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
    'simplytest_ocd',
    'simplytest_tugboat',
    'simplytest_projects',
    'simplytest_launch',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['simplytest_launch']);
  }

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
          'version' => '',
        ],
        'drupalVersion' => '',
        'installProfile' => '',
        'manualInstall' => '0',
      ],
      [
        0 => 'project.shortname: This value should not be blank.',
        1 => 'project.type: This value should not be blank.',
        2 => 'project.type: This value should not be null.',
        3 => 'project.version: This value should not be blank.',
        4 => 'drupalVersion: This value should not be blank.',
        5 => 'installProfile: This value should not be blank.',
      ]
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
        ],
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
          'version' => '8.x-1.9',
        ],
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      [
        0 => 'project.shortname: This value is not valid.',
      ]
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
        ],
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
        'additionalProjects' => [
          [
            'shortname' => 'pathauto',
            'type' => 'module',
            'version' => '8.x-1.8'
          ]
        ],
      ],
      []
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
          'patches' => [
            '/foo/bar/baz.patch',
          ],
        ],
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      [
        'project.patches.0: This value is not a valid URL.'
      ]
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
          'patches' => [
            'http://example.com/foo/bar.patch',
            'ftp://example.com/foo/bar.patch',
          ],
        ],
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      [
        'project.patches.0: This value is not a valid URL.',
        'project.patches.1: This value is not a valid URL.'
      ]
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
          'patches' => [
            'https://example.com/foo/bar.patch',
            'https://example.com/baz/dazzle.patch',
          ],
        ],
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      [
        'project.patches.0: Patches must only originate from a Drupal.org domain.',
      ]
    ];
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
          'patches' => [
            'https://www.drupal.org/foo/bar.patch',
            'https://git.drupalcode.org/baz/dazzle.patch',
          ],
        ],
        'drupalVersion' => '9.1.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      []
    ];
  }

}
