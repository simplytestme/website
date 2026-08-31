<?php

namespace Drupal\simplytest_tugboat\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\tugboat\TugboatClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Simplytest tugboat routes.
 */
class SimplytestTugboatController extends ControllerBase {

  /**
   * How long a computed instance state may be reused, in seconds.
   *
   * The progress page polls this endpoint from every open browser tab, and
   * each uncached hit costs two Tugboat API requests. The TTL collapses all
   * concurrent pollers of one job into a single upstream round-trip per
   * window; it must stay at or below the frontend poll interval or a poller
   * could see the same state twice and conclude nothing is happening.
   */
  private const int STATE_CACHE_TTL = 3;

  /**
   * Browser max-age for finished previews, in seconds.
   *
   * Finite, because a "ready" answer goes stale: sandboxes are deleted two
   * hours after creation, and a permanently cached response would keep
   * redirecting users to a dead preview.
   */
  private const int PREVIEW_MAX_AGE = 60;

  /**
   * The build stages echoed by the preview config, in order.
   */
  private const array PROGRESS_STAGES = [
    'SIMPLYEST_STAGE_DOWNLOAD',
    'SIMPLYEST_STAGE_PATCHING',
    'SIMPLYEST_STAGE_INSTALLING',
    'SIMPLYEST_STAGE_FINALIZE',
    'SIMPLYEST_STAGE_FINISHED',
  ];

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

  public function __construct(Config $config, LoggerInterface $logger, MessengerInterface $messenger, TugboatClient $tugboat_client, /**
   * The cache backend for computed instance states.
   */
  protected CacheBackendInterface $cache) {
    $this->settings = $config;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->tugboatClient = $tugboat_client;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')->get('simplytest_tugboat.settings'),
      $container->get('logger.channel.simplytest_tugboat'),
      $container->get('messenger'),
      $container->get('tugboat.client'),
      $container->get('cache.default')
    );
  }

  public function progress(Request $request, $instance_id, $job_id) {
    return [
      'mount' => [
        '#markup' => Markup::create('<div class="simplytest-react-component" id="progress_mount"></div>'),
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
    $cid = "simplytest_tugboat:instance_state:$job_id";
    if ($cached = $this->cache->get($cid)) {
      return $this->stateResponse($cached->data);
    }

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
      $this->logger->warning('Tugboat returned @code while fetching the state of job @job: @message', [
        '@code' => $exception->getCode(),
        '@job' => $job_id,
        '@message' => $exception->getMessage(),
      ]);
      return new JsonResponse([
        'message' => 'Unable to fetch the sandbox status.',
      ], 502);
    }
    catch (GuzzleException $exception) {
      $this->logger->warning('Failed to reach Tugboat for the state of job @job: @message', [
        '@job' => $job_id,
        '@message' => $exception->getMessage(),
      ]);
      return new JsonResponse([
        'message' => 'Unable to fetch the sandbox status.',
      ], 502);
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
    $logs_data = array_values(array_filter($logs_data, static fn(array $log) => !str_contains((string) $log['message'], 'new (next fetch will store in remotes/origin)') &&
      !str_contains((string) $log['message'], '-> origin/') &&
      !str_contains((string) $log['message'], '[new tag]')));

    $instance_state['logs'] = $logs_data;
    $instance_state['progress'] = $this->calculateProgress($logs_data);

    $this->cache->set($cid, $instance_state, time() + self::STATE_CACHE_TTL);
    return $this->stateResponse($instance_state);
  }

  /**
   * Derives a percentage from the stage markers found in the build log.
   *
   * Each known stage counts once no matter how often its marker appears, so
   * a re-run stage or a duplicated log line can never push the result past
   * 100. The "is ready" line stands in for the final stage because previews
   * built before the FINISHED marker existed only log the ready message.
   *
   * @param array<int, array{message: string}> $logs_data
   *   The filtered build log.
   */
  private function calculateProgress(array $logs_data): int {
    $found = [];
    foreach ($logs_data as $log) {
      $message = (string) $log['message'];
      foreach (self::PROGRESS_STAGES as $stage) {
        if (str_starts_with($message, $stage)) {
          $found[$stage] = TRUE;
        }
      }
      if (str_contains($message, '(simplytest) is ready')) {
        $found['SIMPLYEST_STAGE_FINISHED'] = TRUE;
      }
    }
    return (int) min(100, round(count($found) / count(self::PROGRESS_STAGES) * 100));
  }

  /**
   * Builds the JSON response for a computed instance state.
   *
   * @param array<string, mixed> $instance_state
   *   The computed state.
   */
  private function stateResponse(array $instance_state): JsonResponse {
    // A preview is a finished job: its state only changes when the sandbox
    // expires, so browsers and the page cache may hold it briefly.
    if ($instance_state['type'] === 'preview') {
      $response = new CacheableJsonResponse($instance_state);
      $metadata = (new CacheableMetadata())
        ->setCacheMaxAge(self::PREVIEW_MAX_AGE)
        ->addCacheContexts(['url.path']);
      $response->addCacheableDependency($metadata);
      // Core writes the site-wide max-age into Cache-Control regardless of
      // the response's cacheability metadata; setting it here marks the
      // header as customized so the finite lifetime survives.
      $response->setMaxAge(self::PREVIEW_MAX_AGE);
      return $response;
    }
    // A job is still changing. The short server-side TTL above does the
    // request collapsing; the HTTP response itself must stay uncacheable or
    // pollers would never see fresh state.
    return new JsonResponse($instance_state);
  }

}
