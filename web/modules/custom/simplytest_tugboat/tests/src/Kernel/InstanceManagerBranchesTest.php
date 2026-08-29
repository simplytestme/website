<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_projects_test\BufferedLogger;
use Drupal\simplytest_tugboat\InstanceManagerInterface;

/**
 * Covers the launch paths the happy-path test does not reach.
 *
 * @group simplytest
 * @group simplytest_tugboat
 *
 * @coversDefaultClass \Drupal\simplytest_tugboat\InstanceManager
 */
final class InstanceManagerBranchesTest extends KernelTestBase {

  protected static $modules = [
    'tugboat',
    'simplytest_projects',
    'simplytest_projects_test',
    'simplytest_ocd',
    'simplytest_tugboat',
  ];

  private InstanceManagerInterface $sut;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('simplytest_project');
    $this->installSchema('simplytest_projects', CoreVersionManager::TABLE_NAME);
    $this->installSchema('simplytest_projects', ProjectVersionManager::TABLE_NAME);

    $this->createProject('token', ProjectTypes::MODULE);
    $this->createProject('pathauto', ProjectTypes::MODULE);
    $this->createProject('bootstrap', ProjectTypes::THEME);

    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();

    $this->sut = $this->container->get('simplytest_tugboat.instance_manager');
  }

  /**
   * @covers ::loadPreviewId
   */
  public function testLoadPreviewId(): void {
    self::assertEquals('base-drupal9-id', $this->sut->loadPreviewId('drupal9'));
    self::assertEquals('base-umami-id', $this->sut->loadPreviewId('umami'));
  }

  /**
   * A context with no base preview reports a sentinel rather than failing.
   *
   * @covers ::loadPreviewId
   */
  public function testLoadPreviewIdForUnknownContext(): void {
    self::assertEquals('none', $this->sut->loadPreviewId('drupal42'));
  }

  /**
   * @covers ::loadPreviewId
   */
  public function testLoadPreviewIdWithoutBasePrefix(): void {
    // Without the base prefix the provider label no longer matches.
    self::assertEquals('none', $this->sut->loadPreviewId('drupal9', FALSE));
  }

  /**
   * A one-click demo builds its config from the plugin, not the submission.
   *
   * @covers ::launchInstance
   */
  public function testLaunchOneClickDemo(): void {
    $this->sut->launchInstance([
      'oneclickdemo' => 'oneclickdemo_umami',
      'manualInstall' => FALSE,
    ]);

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    self::assertEquals('base-umami-id', $payload['base']);
    self::assertEquals('kerneltestrepo', $payload['repo']);

    $commands = $payload['config']['services']['php']['commands']['build'];
    self::assertContains('cd ${DOCROOT} && ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y', $commands);
  }

  /**
   * A manual install skips the install step in the generated config.
   *
   * @covers ::launchInstance
   */
  public function testLaunchWithManualInstall(): void {
    $this->sut->launchInstance($this->submission(['manualInstall' => TRUE]));

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    $commands = implode("\n", $payload['config']['services']['php']['commands']['build']);
    self::assertStringNotContainsString('drush si ', $commands);
  }

  /**
   * Patches are applied, and empty entries are dropped.
   *
   * @covers ::launchInstance
   */
  public function testLaunchWithPatches(): void {
    $submission = $this->submission();
    $submission['project']['patches'] = [
      'https://www.drupal.org/files/issues/example.patch',
      '',
    ];
    $this->sut->launchInstance($submission);

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    $commands = implode("\n", $payload['config']['services']['php']['commands']['build']);
    self::assertStringContainsString('https://www.drupal.org/files/issues/example.patch', $commands);
  }

  /**
   * Additional projects are resolved to their stored project type.
   *
   * @covers ::launchInstance
   */
  public function testLaunchWithAdditionalProjects(): void {
    $this->sut->launchInstance($this->submission([
      'additionalProjects' => [
        ['shortname' => 'pathauto', 'version' => '8.x-1.8', 'patches' => []],
        ['shortname' => 'bootstrap', 'version' => '8.x-3.24', 'patches' => []],
      ],
    ]));

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    $commands = implode("\n", $payload['config']['services']['php']['commands']['build']);
    self::assertStringContainsString('composer require drupal/pathauto:1.8', $commands);
    self::assertStringContainsString('composer require drupal/bootstrap:3.24', $commands);
    // The theme is enabled as a theme, which needs the stored project type.
    self::assertStringContainsString('drush theme:enable bootstrap', $commands);
  }

  /**
   * The major version of the requested core drives the base preview.
   *
   * @covers ::launchInstance
   */
  public function testLaunchUsesMajorVersionForBasePreview(): void {
    $this->sut->launchInstance($this->submission(['drupalVersion' => '10.1.0']));

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    self::assertEquals('base-drupal10-id', $payload['base']);
  }

  /**
   * The response carries the identifiers the launcher polls with.
   *
   * @covers ::launchInstance
   */
  public function testLaunchReturnsPreviewIdentifiers(): void {
    $result = $this->sut->launchInstance($this->submission());

    self::assertEquals('abc123', $result['tugboat']['preview_id']);
    self::assertEquals('ac123', $result['tugboat']['job_id']);
    self::assertEquals(
      ['https://api.tugboatqa.com/v3/previews/abc123'],
      $result['tugboat']['job_url'],
    );
    self::assertArrayHasKey('headers', $result['meta']);
  }

  /**
   * A missing base preview is reported so it can be noticed in production.
   *
   * @covers ::loadPreviewId
   */
  public function testMissingBasePreviewIsLogged(): void {
    // An empty provider label list makes the lookup come up short.
    $this->sut->loadPreviewId('drupal42');

    $logger = $this->container->get('simplytest_projects_test.logger');
    // The sentinel is not empty, so nothing is logged today.
    self::assertFalse($logger->hasMessageContaining('No base preview for'));
  }

  /**
   * @param array<string, mixed> $overrides
   *
   * @return array<string, mixed>
   */
  private function submission(array $overrides = []): array {
    return $overrides + [
      'project' => [
        'shortname' => 'token',
        'type' => 'module',
        'sandbox' => FALSE,
        'version' => '8.x-1.9',
      ],
      'drupalVersion' => '9.3.2',
      'installProfile' => 'standard',
      'manualInstall' => FALSE,
    ];
  }

  private function createProject(string $shortname, string $type): void {
    SimplytestProject::create([
      'title' => ucfirst($shortname),
      'shortname' => $shortname,
      'sandbox' => "0",
      'type' => $type,
    ])->save();
  }

}
