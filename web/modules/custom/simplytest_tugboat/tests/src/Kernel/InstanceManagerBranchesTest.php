<?php

declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Kernel;

use Drupal\simplytest_tugboat\InstanceManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simplytest_projects\CoreVersionManager;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use Drupal\simplytest_projects\ProjectTypes;
use Drupal\simplytest_projects\ProjectVersionManager;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Drupal\simplytest_tugboat\LaunchRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the launch paths the happy-path test does not reach.
 *
 *
 */
#[CoversClass(InstanceManager::class)]
#[CoversMethod(InstanceManager::class, 'loadPreviewId')]
#[CoversMethod(InstanceManager::class, 'launchInstance')]
#[Group('simplytest')]
#[Group('simplytest_tugboat')]
#[RunTestsInSeparateProcesses]
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
    $this->installSchema('simplytest_tugboat', LaunchRecorder::TABLE_NAME);

    $this->createProject('token', ProjectTypes::MODULE);
    $this->createProject('pathauto', ProjectTypes::MODULE);
    $this->createProject('bootstrap', ProjectTypes::THEME);

    $this->config('tugboat.settings')
      ->set('repository_id', 'kerneltestrepo')
      ->save();

    $this->sut = $this->container->get('simplytest_tugboat.instance_manager');
  }

  public function testLoadPreviewId(): void {
    self::assertEquals('base-drupal9-id', $this->sut->loadPreviewId('drupal9'));
    self::assertEquals('base-umami-id', $this->sut->loadPreviewId('umami'));
  }

  /**
   * A context with no base preview reports a sentinel rather than failing.
   */
  public function testLoadPreviewIdForUnknownContext(): void {
    self::assertEquals('none', $this->sut->loadPreviewId('drupal42'));
    $logger = $this->container->get('simplytest_projects_test.logger');
    self::assertTrue($logger->hasMessageContaining('No base preview for drupal42'));
  }

  /**
   * A base name is never matched without its prefix.
   */
  public function testLoadPreviewIdIgnoresSandboxes(): void {
    // A sandbox named after the branch it was built from is not a base.
    self::assertEquals('none', $this->sut->loadPreviewId('master'));
  }

  /**
   * A one-click demo is a clone of its base preview, which is the demo.
   */
  public function testLaunchOneClickDemo(): void {
    $this->config('tugboat.settings')->set('sandbox_lifetime', 7200)->save();
    $result = $this->sut->launchInstance([
      'oneclickdemo' => 'oneclickdemo_umami',
      'manualInstall' => FALSE,
    ]);

    $state = $this->container->get('state');
    self::assertNull($state->get('https://api.tugboatqa.com/v3/previews'));
    $payload = $state->get('https://api.tugboatqa.com/v3/previews/base-umami-id/clone');
    // The progress page looks for this name in the ready line.
    self::assertEquals('simplytest', $payload['name']);
    $expected = $this->container->get('datetime.time')->getRequestTime() + 7200;
    self::assertEquals($expected, strtotime((string) $payload['expires']));

    self::assertEquals('clone123', $result['tugboat']['preview_id']);
    self::assertEquals('cj123', $result['tugboat']['job_id']);
    self::assertEquals(['https://api.tugboatqa.com/v3/previews/clone123'], $result['tugboat']['job_url']);
    self::assertEquals('clone123', $this->loadOnlyRecord()->preview_id);
  }

  /**
   * A demo with no usable base is built from scratch, from the plugin.
   */
  public function testLaunchOneClickDemoWithoutBase(): void {
    // The mocked repository has no base-starshot preview.
    $this->sut->launchInstance([
      'oneclickdemo' => 'starshot',
      'manualInstall' => FALSE,
    ]);

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    self::assertEquals('none', $payload['base']);
    self::assertEquals('kerneltestrepo', $payload['repo']);
    $commands = implode("\n", $payload['config']['services']['php']['commands']['build']);
    self::assertStringContainsString('composer create-project drupal/cms', $commands);
    self::assertStringContainsString('drush si ', $commands);
  }

  /**
   * A sandbox carries the expiry Tugboat deletes it on.
   *
   * Nothing else expires a sandbox, so one launched without this stays on
   * Tugboat until somebody deletes it by hand.
   */
  public function testLaunchExpiresTheSandbox(): void {
    $this->config('tugboat.settings')->set('sandbox_lifetime', 7200)->save();
    $this->sut->launchInstance($this->submission());

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    $expected = $this->container->get('datetime.time')->getRequestTime() + 7200;
    self::assertEquals($expected, strtotime((string) $payload['expires']));
  }

  /**
   * A manual install skips the install step in the generated config.
   */
  public function testLaunchWithManualInstall(): void {
    $this->sut->launchInstance($this->submission(['manualInstall' => TRUE]));

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    $commands = implode("\n", $payload['config']['services']['php']['commands']['build']);
    self::assertStringNotContainsString('drush si ', $commands);
  }

  /**
   * Patches are applied, and empty entries are dropped.
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
   */
  public function testLaunchUsesMajorVersionForBasePreview(): void {
    $this->sut->launchInstance($this->submission(['drupalVersion' => '10.1.0']));

    $payload = $this->container->get('state')->get('https://api.tugboatqa.com/v3/previews');
    self::assertEquals('base-drupal10-id', $payload['base']);
  }

  /**
   * The response carries the identifiers the launcher polls with.
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
   * Every launch is recorded, so the report knows what people evaluate.
   */
  public function testLaunchIsRecorded(): void {
    $submission = $this->submission();
    $submission['project']['patches'] = ['https://www.drupal.org/files/issues/example.patch'];
    $this->sut->launchInstance($submission);

    $record = $this->loadOnlyRecord();
    self::assertEquals(LaunchRecorder::STATUS_LAUNCHED, $record->status);
    self::assertEquals('abc123', $record->preview_id);
    self::assertEquals('token', $record->project);
    self::assertEquals(ProjectTypes::MODULE, $record->project_type);
    self::assertEquals('8.x-1.9', $record->project_version);
    self::assertEquals('9.3.2', $record->core_version);
    self::assertEquals('standard', $record->install_profile);
    self::assertEquals(1, (int) $record->patch_count);
  }

  /**
   * A one click demo is recorded by plugin ID, with no project details.
   */
  public function testOneClickDemoIsRecorded(): void {
    $this->sut->launchInstance([
      'oneclickdemo' => 'oneclickdemo_umami',
      'manualInstall' => FALSE,
    ]);

    $record = $this->loadOnlyRecord();
    self::assertEquals(LaunchRecorder::STATUS_LAUNCHED, $record->status);
    self::assertEquals('oneclickdemo_umami', $record->one_click_demo);
    self::assertEquals('', $record->project);
  }

  /**
   * A launch Tugboat never accepted is still recorded, as a failure.
   */
  public function testFailedLaunchIsRecorded(): void {
    // This repository ID makes the Tugboat API fail in the mocked middleware.
    $this->config('tugboat.settings')->set('repository_id', 'brokenrepo')->save();

    try {
      $this->sut->launchInstance($this->submission());
      self::fail('Expected the Tugboat request to fail.');
    }
    catch (\Throwable) {
      // The exception still has to reach the caller, which is what turns into
      // the 503 the launcher shows.
    }

    $record = $this->loadOnlyRecord();
    self::assertEquals(LaunchRecorder::STATUS_FAILED, $record->status);
    self::assertEquals('', $record->preview_id);
    self::assertEquals('token', $record->project);
  }

  private function loadOnlyRecord(): \stdClass {
    $records = $this->container->get('database')
      ->select(LaunchRecorder::TABLE_NAME, 'r')
      ->fields('r')
      ->execute()
      ->fetchAll();
    self::assertCount(1, $records);
    return $records[0];
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
