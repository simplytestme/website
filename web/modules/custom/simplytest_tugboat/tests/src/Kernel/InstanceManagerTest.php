<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;

/**
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\InstanceManager
 */
final class InstanceManagerTest extends KernelTestBase {

//  protected $runTestInSeparateProcess = FALSE;

  protected static $modules = [
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);

    SimplytestProject::create([
      'title' => 'Token',
      'shortname' => 'token',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ])->save();
    SimplytestProject::create([
      'title' => 'Pathauto',
      'shortname' => 'pathauto',
      'sandbox' => "0",
      'type' => ProjectTypes::MODULE,
    ])->save();
    SimplytestProject::create([
      'title' => 'Bootstrap',
      'shortname' => 'bootstrap',
      'sandbox' => "0",
      'type' => ProjectTypes::THEME,
    ])->save();

    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();
  }

  /**
   * @covers ::launchInstance
   */
  public function testLaunchInstance(): void {
    $sut = $this->container->get('simplytest_tugboat.instance_manager');

    $data = [
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => false,
        'version' => '8.x-1.9',
      ],
      'drupalVersion' => '9.3.2',
      'installProfile' => 'umami',
      'manualInstall' => '0',
      'additionalProjects' => [
        [
          'shortname' => 'pathauto',
          'type' => 'module',
          'version' => '8.x-1.8',
          'patches' => [],
        ],
        [
          'shortname' => 'bootstrap',
          'type' => 'theme',
          'version' => '8.x-3.24',
          'patches' => [],
        ],
      ],
    ];
    $sut->launchInstance($data);

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');

    $expected = [
      'ref' => 'master',
      'config' => [
        'services' => [
          'php' => [
            'image' => 'tugboatqa/php:8.1-apache',
            'default' => true,
            'depends' => 'mysql',
            'commands' => [
              'build' => [
                'docker-php-ext-install opcache',
                'a2enmod headers rewrite',
                'composer self-update',
                'rm -rf "${DOCROOT}"',
                'composer -n create-project drupal/recommended-project:9.3.2 stm --no-install',
                'cd stm && composer config minimum-stability dev',
                'cd stm && composer config prefer-stable true',
                'cd stm && composer require --dev --no-install drupal/core:9.3.2 drupal/core-dev:9.3.2',
                'cd stm && composer require --dev --no-install phpspec/prophecy-phpunit:^2',
                'cd stm && composer require --no-install drush/drush',
                'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
                'echo "SIMPLYEST_STAGE_DOWNLOAD"',
                'cd stm && composer require drupal/token:1.9 --no-install',
                'cd stm && composer require drupal/pathauto:1.8 --no-install',
                'cd stm && composer require drupal/bootstrap:3.24 --no-install',
                'echo "SIMPLYEST_STAGE_PATCHING"',
                'cd stm && composer update --no-ansi',
                'echo "SIMPLYEST_STAGE_INSTALLING"',
                'cd "${DOCROOT}" && ../vendor/bin/drush si umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.logging error_level verbose -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush en token -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush en pathauto -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush theme:enable bootstrap -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme default bootstrap -y',
                'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
                'mkdir -p ${DOCROOT}/sites/default/files',
                'mkdir -p ${DOCROOT}/sites/default/files/private',
                'chown -R www-data:www-data ${DOCROOT}/sites/default',
                'chown -R www-data:www-data ${DOCROOT}/modules',
                'echo "max_allowed_packet=33554432" >> /etc/my.cnf',
                'echo "SIMPLYEST_STAGE_FINALIZE"',
              ],
            ],
          ],
          'mysql' => [
            'image' => 'tugboatqa/mysql:5',
          ],
        ]
      ],
      'name' => 'simplytest',
      'repo' => 'kerneltestrepo',
      'base' => 'base-drupal9-id',
    ];
    self::assertEquals($expected, $payload);
  }
}

