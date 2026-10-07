<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\simplytest_tugboat\InstanceManager;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Exception\ServerException;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_tugboat\Exception\LaunchLimitExceededException;
use Drupal\simplytest_tugboat\LaunchRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversClass(InstanceManager::class)]
#[CoversMethod(InstanceManager::class, 'launchInstance')]
#[Group('simplytest')]
#[Group('simplytest_tugboat')]
#[RunTestsInSeparateProcesses]
final class InstanceManagerTest extends KernelTestBase {

  private const array DEMO_SUBMISSION = [
    'oneclickdemo' => 'oneclickdemo_dashi',
    'manualInstall' => FALSE,
  ];

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
    $this->installSchema('simplytest_tugboat', LaunchRecorder::TABLE_NAME);
    $this->installConfig(['simplytest_tugboat']);

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
                'php -m | grep -qi bcmath || docker-php-ext-install bcmath',
                'php -m | grep -qi mysqli || docker-php-ext-install mysqli',
                'a2enmod headers rewrite',
                'command -v yq > /dev/null || (wget -q https://github.com/mikefarah/yq/releases/latest/download/yq_linux_amd64 -O /usr/local/bin/yq && chmod +x /usr/local/bin/yq)',
                'composer config --global policy.advisories.block false',
                '[ "$(cd "${TUGBOAT_ROOT}/stm" 2>/dev/null && composer show drupal/core --format=json 2>/dev/null | jq -r \'.versions[0]\')" = "9.3.2" ] && echo "Reusing Drupal 9.3.2 from the base preview" || (rm -rf "${DOCROOT}" "${TUGBOAT_ROOT}/stm" && cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/recommended-project:9.3.2 stm --no-install && cd "${TUGBOAT_ROOT}/stm" && composer config minimum-stability dev && cd "${TUGBOAT_ROOT}/stm" && composer config prefer-stable true && cd "${TUGBOAT_ROOT}/stm" && composer require --no-install drush/drush && ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}")',
                'cd "${TUGBOAT_ROOT}/stm" && composer require --dev --no-install drupal/core:9.3.2',
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
                'deps=$(yq -o=json \'.dependencies // []\' ${DOCROOT}/themes/contrib/bootstrap/bootstrap.info.yml | jq -r \'.[] | split(":")[1]\' | xargs); [ -z "$deps" ] || ${DOCROOT}/../vendor/bin/drush en $deps -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush theme:enable bootstrap -y',
                'cd "${DOCROOT}" && ../vendor/bin/drush config-set system.theme default bootstrap -y',
                'cd "${DOCROOT}" && echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> sites/default/settings.php',
                'mkdir -p ${DOCROOT}/sites/default/files',
                'mkdir -p ${DOCROOT}/sites/default/files/private',
                'chown -R www-data:www-data ${DOCROOT}/sites/default',
                'chown -R www-data:www-data ${DOCROOT}/modules',
                'echo "SIMPLYEST_STAGE_FINALIZE"',
                'cd "${DOCROOT}" && echo "SIMPLYTEST_LOGIN_URL $(../vendor/bin/drush uli --uri="${TUGBOAT_DEFAULT_SERVICE_URL}" --no-browser /)"',
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
      // Nothing else expires a sandbox, so a launch that goes out without this
      // stays on Tugboat until somebody deletes it by hand.
      'expires' => date(
        \DateTimeInterface::RFC3339,
        $this->container->get('datetime.time')->getRequestTime()
        + (int) $this->config('tugboat.settings')->get('sandbox_lifetime'),
      ),
    ];
    self::assertEquals($expected, $payload);
  }

  /**
   * A client that reaches the limit cannot launch until the window passes.
   */
  public function testLaunchLimit(): void {
    $this->config('simplytest_tugboat.settings')
      ->set('launch_limit', 2)
      ->set('launch_limit_window', 600)
      ->save();
    $sut = $this->container->get('simplytest_tugboat.instance_manager');
    $sut->launchInstance(self::DEMO_SUBMISSION);
    $sut->launchInstance(self::DEMO_SUBMISSION);

    try {
      $sut->launchInstance(self::DEMO_SUBMISSION);
      self::fail('The launch past the limit went through.');
    }
    catch (LaunchLimitExceededException $e) {
      self::assertEquals(600, $e->retryAfter);
    }
  }

  /**
   * A launch Tugboat fails still counts, so retrying in a loop is limited.
   */
  public function testFailedLaunchCountsTowardLimit(): void {
    $this->config('simplytest_tugboat.settings')->set('launch_limit', 1)->save();
    $this->config('tugboat.settings')->set('repository_id', 'brokenrepo')->save();
    $sut = $this->container->get('simplytest_tugboat.instance_manager');

    $tugboat_error = NULL;
    try {
      $sut->launchInstance(self::DEMO_SUBMISSION);
    }
    catch (\Throwable $e) {
      $tugboat_error = $e;
    }
    self::assertInstanceOf(ServerException::class, $tugboat_error, 'Tugboat accepted a launch for the broken repository.');

    $this->expectException(LaunchLimitExceededException::class);
    $sut->launchInstance(self::DEMO_SUBMISSION);
  }

}
