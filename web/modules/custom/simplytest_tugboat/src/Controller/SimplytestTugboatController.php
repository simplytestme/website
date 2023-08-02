<?php

namespace Drupal\simplytest_tugboat\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\tugboat\TugboatClient;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Simplytest tugboat routes.
 */
class SimplytestTugboatController extends ControllerBase {

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The logger channel for this module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Tugboat client.
   * @var \Drupal\tugboat\TugboatClient
   */
  protected $tugboatClient;

  public function __construct(Config $config, LoggerInterface $logger, MessengerInterface $messenger, TugboatClient $tugboat_client) {
    $this->settings = $config;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->tugboatClient = $tugboat_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('simplytest_tugboat.settings'),
      $container->get('logger.channel.simplytest_tugboat'),
      $container->get('messenger'),
      $container->get('tugboat.client')
    );
  }

  public function progress(Request $request, $instance_id, $job_id) {
    return [
      'mount' => [
        '#markup' => Markup::create('<div class="simplytest-react-component bg-gradient-to-r from-flat-blue text-white" id="progress_mount"></div>'),
        '#attached' => [
          'library' => [
            'simplytest_theme/launcher',
          ],
          'drupalSettings' => [
            // Pass custom launcher values to drupalSettings.
            'instanceId' => $instance_id,
            'jobId' => $job_id,
            'stateUrl' => Url::fromRoute('simplytest_tugboat.state', [
              'instance_id' => $instance_id,
              'job_id' => $job_id
            ])->toString(),
          ],
        ],
      ],
    ];
  }

  public function instanceState($instance_id, $job_id) {
    try {
      $status_response = $this->tugboatClient->requestWithApiKey('GET', "jobs/$job_id");
      $status_data = Json::decode((string) $status_response->getBody());
      $log_response = $this->tugboatClient->requestWithApiKey('GET', "jobs/$job_id/log");
      $logs_data = Json::decode((string) $log_response->getBody());
    }
    catch (ClientException $exception) {
      if ($exception->getCode() === 404) {
        return new JsonResponse([
          'message' => 'Sandbox instance no longer exists',
        ], $exception->getCode());
      }
      throw $exception;
    }
    catch (\Exception $e) {
      throw $e;
    }

    $instance_state = [
      'progress' => 0,
      'createdAt' => $status_data['createdAt'],
      'updatedAt' => $status_data['updatedAt'],
      'type' => $status_data['type'],
      'url' => null,
    ];

    if ($status_data['type'] === 'preview') {
      // If the preview is suspended, use the status it was suspended at.
      if (isset($status_data['suspended'])) {
        $instance_state['state'] = $status_data['suspended'];
      }
      else {
        $instance_state['state'] = $status_data['state'];
      }
      $instance_state['url'] = $status_data['url'];
    }
    elseif ($status_data['type'] === 'job') {
      $instance_state['state'] = $status_data['action'];
    }
    else {
      throw new \RuntimeException('Unexpected job type');
    }

    // Trim out some git logs.
    $logs_data = array_values(array_filter($logs_data, static function(array $log) {
      return strpos($log['message'], 'new (next fetch will store in remotes/origin)') === FALSE &&
        strpos($log['message'], '-> origin/') === FALSE &&
        strpos($log['message'], '[new tag]') === FALSE;
    }));

    $instance_state['logs'] = $logs_data;
    // Filter the logs to find our progress markers and the complete message.
    $progress_steps = array_filter($logs_data, static function(array $logs) {
      return strpos($logs['message'], 'SIMPLYEST_STAGE_') === 0 || strpos($logs['message'], '(simplytest) is ready') !== FALSE;
    });
    $total_steps = ['SIMPLYEST_STAGE_DOWNLOAD', 'SIMPLYEST_STAGE_PATCHING', 'SIMPLYEST_STAGE_INSTALLING', 'SIMPLYEST_STAGE_FINALIZE', 'SIMPLYEST_STAGE_FINISHED'];
    $instance_state['progress'] = (count($progress_steps) / count($total_steps)) * 100;

    // If we have a preview, that means the job completed and we can cache this
    // result.
    if ($status_data['type'] === 'preview') {
      return new CacheableJsonResponse($instance_state);
    }
    // @todo infer cache headers from Tugboat and return CacheableJsonResponse.
    return new JsonResponse($instance_state);
  }

}
