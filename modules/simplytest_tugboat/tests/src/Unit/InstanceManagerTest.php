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

  protected function setUp(): void {
    if (getenv('TUGBOAT_API_KEY') === null && getenv('TUGBOAT_REPOSITORY_ID')) {
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

  public function testDrupal7() {
    $instance_id = Crypt::randomBytesBase64();
    $hash = Crypt::randomBytesBase64();

    $result = $this->instanceManager->launchInstance([
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
      'instance_id' => $instance_id,
      'hash' => $hash,
    ]);
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
    self::assertEquals('ready', $status_data['state'], var_export($status_data, TRUE));
    $sandbox_id = $status_data['id'];
    $this->tugboatClient->requestWithApiKey('DELETE', "previews/$sandbox_id");

  }

}
