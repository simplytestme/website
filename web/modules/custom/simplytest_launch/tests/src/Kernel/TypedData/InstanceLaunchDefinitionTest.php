<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_launch\Kernel\TypedData;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_launch\Plugin\DataType\InstanceLaunch;
use Drupal\simplytest_launch\TypedData\InstanceLaunchDefinition;
use Drupal\simplytest_projects\CoreVersionManager;
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
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    // The one core release the valid submissions below ask for.
    $this->container->get('database')->insert(CoreVersionManager::TABLE_NAME)
      ->fields([
        'version' => '9.1.0',
        'major' => 9,
        'minor' => 1,
        'patch' => 0,
        'extra' => '',
        'vcs_label' => '9.1.0',
        'insecure' => 0,
      ])
      ->execute();
  }

  /**
   * @dataProvider instanceLaunchData
   */
  public function testValidation(array $data, array $expected_violations) {
    $typed_data_manager = $this->container->get('typed_data_manager');
    $data = $typed_data_manager->create(InstanceLaunchDefinition::create(), $data);
    assert($data instanceof InstanceLaunch);
    $constraints = $data->validate();
    $messages = array_map(static fn(ConstraintViolationInterface $violation) => sprintf("%s: %s", $violation->getPropertyPath(), $violation->getMessage()), \iterator_to_array($constraints));
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
        1 => 'project.version: This value should not be blank.',
        2 => 'drupalVersion: This value should not be blank.',
        3 => 'installProfile: The install profile must be one of standard, minimal, demo_umami.',
      ]
    ];
    // Anything the form would not offer is rejected, however well formed.
    yield [
      [
        'project' => [
          'shortname' => 'token',
          'type' => 'module',
          'sandbox' => false,
          'version' => '8.x-1.9',
        ],
        'drupalVersion' => '9.99.0',
        'installProfile' => 'umami',
        'manualInstall' => '0',
      ],
      [
        0 => 'drupalVersion: There is no Drupal core release with the version 9.99.0.',
        1 => 'installProfile: The install profile must be one of standard, minimal, demo_umami.',
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
        'installProfile' => 'demo_umami',
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
        'installProfile' => 'demo_umami',
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
        'installProfile' => 'demo_umami',
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
        'installProfile' => 'demo_umami',
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
        'installProfile' => 'demo_umami',
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
        'installProfile' => 'demo_umami',
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
        'installProfile' => 'demo_umami',
        'manualInstall' => '0',
      ],
      []
    ];
  }

}
