<?php declare(strict_types=1);

namespace Drupal\Tests\simplytest_tugboat\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_tugboat\InstanceManager;
use Drupal\simplytest_tugboat\PreviewConfigGenerator;
use Drupal\Tests\UnitTestCase;
use Drupal\tugboat\TugboatClient;
use GuzzleHttp\HandlerStack;
use Psr\Log\NullLogger;

final class InstanceManagerTest extends UnitTestCase {

  private InstanceManager $instanceManager;
  private TugboatClient $tugboatClient;
  private array $previewIds = [];

  protected function setUp(): void {
    if (empty(getenv('TUGBOAT_API_KEY')) || empty(getenv('TUGBOAT_REPOSITORY_ID'))) {
      $this->markTestSkipped('Requires TUGBOAT env variables.');
    }
    parent::setUp();

    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $tugboat_settings = $this->prophesize(Config::class);
    $tugboat_settings->get('token')->willReturn(getenv('TUGBOAT_API_KEY'));
    $tugboat_settings->get('repository_id')->willReturn(getenv('TUGBOAT_REPOSITORY_ID'));
    $tugboat_settings->get('repository_base')->willReturn('master');
    $config_factory->get('tugboat.settings')->willReturn($tugboat_settings->reveal());

    $this->tugboatClient = new TugboatClient(
      new ClientFactory(HandlerStack::create()),
      $config_factory->reveal()
    );

    $preview_config_generator = new PreviewConfigGenerator(
      $this->prophesize(OneClickDemoPluginManager::class)->reveal()
    );

    $this->instanceManager = new InstanceManager(
      $config_factory->reveal(),
      new LoggerChannel('foo'),
      $this->prophesize(ModuleHandlerInterface::class)->reveal(),
      $this->tugboatClient,
      $preview_config_generator
    );
  }

  public function testDrupal7Core() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '7.x-dev',
      'project' => [
        'version' => '7.x-dev',
        'shortname' => 'drupal',
        'type' => 'Drupal core',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  public function testDrupal7() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '7.77',
      'project' => [
        'version' => '7.x-1.0',
        'shortname' => 'token',
        'type' => 'Module',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  public function testDrupal7ManualInstall() {
    $this->doTest([
      'manualInstall' => TRUE,
      'installProfile' => 'standard',
      'drupalVersion' => '7.80',
      'project' => [
        'version' => '7.80',
        'shortname' => 'drupal',
        'type' => 'Drupal core',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  public function testDrupal7WithPatches() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '7.77',
      'project' => [
        'version' => '7.x-1.0',
        'shortname' => 'token',
        'type' => 'Module',
      ],
      'patches' => [
        'https://www.drupal.org/files/issues/2021-03-02/array-to-string-conversion-notice-3048863-9.patch'
      ],
      'additionals' => [],
    ]);
  }

  public function testDrupal8() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '8.9.18',
      'project' => [
        'version' => '8.x-1.9',
        'shortname' => 'token',
        'type' => 'Module',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  public function testDrupal8WithAdditionals() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '8.9.18',
      'project' => [
        'version' => '8.x-3.0-beta1',
        'shortname' => 'password_policy',
        'type' => 'Module',
      ],
      'patches' => [],
      'additionals' => [
        [
          'version' => '8.x-1.0-beta2',
          'shortname' => 'password_policy_pwned',
          'type' => 'Module',
        ]
      ],
    ]);
  }

  public function testDrupal9Core() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '9.3.x-dev',
      'project' => [
        'version' => '9.3.x-dev',
        'shortname' => 'drupal',
        'type' => 'Drupal core',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  public function testDrupal9() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '9.2.4',
      'project' => [
        'version' => '8.x-1.9',
        'shortname' => 'token',
        'type' => 'Module',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  public function testDrupal9WithAdditionals() {
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      'drupalVersion' => '9.2.4',
      'project' => [
        'version' => '8.x-3.0-beta1',
        'shortname' => 'password_policy',
        'type' => 'Module',
      ],
      'patches' => [],
      'additionals' => [
        [
          'version' => '8.x-1.0-beta2',
          'shortname' => 'password_policy_pwned',
          'type' => 'Module',
        ]
      ],
    ]);
  }

  public function testPanopoly() {
    $this->markTestIncomplete('Weird issues afoot, like missing pathauto and ctools dependency');
    $this->doTest([
      'manualInstall' => FALSE,
      'installProfile' => 'standard',
      // ?? Panopoly works on D9, but Drupal.org Compsoer facade says no (~8.0)
      'drupalVersion' => '8.9.18',
      'project' => [
        'version' => '8.x-2.x-dev',
        'shortname' => 'panopoly',
        'type' => 'Distribution',
      ],
      'patches' => [],
      'additionals' => [],
    ]);
  }

  /**
   * Do the test.
   *
   * We do not use a dataProvider, which would normally make a lot of sense.
   * However, this makes it easier to run a specific test scenario since this
   * involves a fully functional integration with Tugboat.
   *
   * @param array $submission
   *   The submission.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function doTest(array $submission) {
    $submission['instance_id'] = Crypt::randomBytesBase64();
    $submission['hash'] = Crypt::randomBytesBase64();

    $result = $this->instanceManager->launchInstance($submission);
    $job_id = $result['tugboat']['job_id'];

    $finished = false;
    while ($finished === false) {
      try {
        $status_response = $this->tugboatClient->requestWithApiKey('GET', "jobs/$job_id");
        $status_data = Json::decode((string) $status_response->getBody());
        if ($status_data['type'] === 'preview') {
          $finished = true;
        }
        sleep(2);
      }
      catch (\Throwable $e) {
        $finished = TRUE;
        $this->fail($e->getMessage());
      }
    }
    $log_response = $this->tugboatClient->requestWithApiKey('GET', "jobs/$job_id/log");
    $logs_data = Json::decode((string) $log_response->getBody());

    // Delete preview so that test failure doesn't leave it lingering.
    $sandbox_id = $status_data['id'];
    $this->tugboatClient->requestWithApiKey('DELETE', "previews/$sandbox_id");

    self::assertEquals('ready', $status_data['state'], var_export($logs_data, TRUE));
  }

}
