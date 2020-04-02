<?php

namespace Drupal\simplytest_tugboat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
   * The instance manager service.
   *
   * @var \Drupal\simplytest_tugboat\InstanceManagerInterface
   */
  protected $instanceManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger channel for this module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service;
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Tugboat Execute service.
   *
   * @var \Drupal\tugboat\TugboatExecute
   */
  protected $tugboatExecute;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->settings = $this->config('simplytest_tugboat.settings');
    $instance->instanceManager = $container->get('simplytest_tugboat.instance_manager');
    $instance->fileSystem = $container->get('file_system');
    $instance->logger = $this->getLogger('simplytest_tugboat');
    $instance->messenger = $container->get('messenger');
    $instance->tugboatExecute = $container->get('tugboat.execute');
    return $instance;
  }

  /**
   * Start and run the tugboat execution.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   */
  public function provision($instance_id) {
    $state = $this->instanceManager->getStatusState($instance_id);

    if ($state['code'] !== InstanceManagerInterface::ENQUEUE) {
      return new Response();
    }

    $this->instanceManager->updateStatus($instance_id, InstanceManagerInterface::SPAWNED);

    // Let's run this in Tugboat.
    $tugboat_repo = $this->settings->get('tugboat_repository_id');

    $return_data = [];
    $error_string = '';

    $drupal_path = 'public://instance-log';
    $this->fileSystem->prepareDirectory($drupal_path, FileSystemInterface::CREATE_DIRECTORY);
    $result_path = "$drupal_path/$instance_id-result.txt";
    $output_path = "$drupal_path/$instance_id-output.txt";
    $error_path = "$drupal_path/$instance_id-error.txt";

    //$return_status = TRUE;

    $context = simplytest_tugboat_context_load($instance_id);
    $this->instanceManager->updateStatus($instance_id, InstanceManagerInterface::PREPARE);

    // Load the ID of the correct base preview ID.
    $base_preview_id = simplytest_tugboat_load_preview_id($context, TRUE);

    $this->logger->notice('message', 'Trying to load base preview ' . $base_preview_id);

    // Run the tugboat command.
    $command = "create preview $instance_id repo=$tugboat_repo preview=$instance_id base=$base_preview_id";
    $this->logger->notice($command);
    $return_status = $this->tugboatExecute->execute($command, $return_data, $error_string, NULL, $result_path, $output_path, $error_path);

    $this->logger->notice('Error: ' . $error_string);
    $this->logger->notice('Return data: ' . var_export($return_data, TRUE));

    if (!$return_status or empty($return_data['url'])) {
      $this->messenger->addMessage($this->t('An instance could not be created at this time! Please try again later.'));
      $this->instanceManager->updateStatus($instance_id, InstanceManagerInterface::FAILED);

      if ($error_string) {
        $this->logger->notice("Failed to create sandbox. Error from Tugboat: <pre>$error_string</pre>");
      }
      else {
        $this->logger->notice('Failed to create instance. No error data returned from Tugboat!');
      }
    }
    else {
      $url = $return_data['url'];
      $this->logger->notice('simplytest_tugboat', "Submitted Tugboat sandbox: $instance_id");
      // Set it to finished so it redirects.

      simplytest_tugboat_url_update($instance_id, $url);
      $this->instanceManager->updateStatus($instance_id, InstanceManagerInterface::FINISHED);
    }

    return new Response();
  }

  /**
   * Progress indicator page for a specific submission.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   */
  public function progress($instance_id) {

    // Make sure this submission is available.
    $state = $this->instanceManager->getStatusState($instance_id);
    if ($state === FALSE) {
      $this->messenger->addError($this->t('The requested submission is not available.'));
      return $this->redirect('<front>');
    }

    // If the siterequest came by the meta no_js refresh.
    if (isset($_GET['no_js'])) {
      // If this submission is finished, HTTP redirect.
      if ($state['percent'] == '100') {
        $this->goto($instance_id);
        return new Response();
      }
    }

    // Check for an established URL, forward if exists.
    if ($state['percent'] == '100') {
      $url = $this->instanceManager->loadUrl($instance_id);

      // Redirect through a 'Refresh' meta tag if JavaScript is disabled.
      $meta_refresh = [
        '#prefix' => '<noscript>',
        '#suffix' => '</noscript>',
        '#tag' => 'meta',
        '#attributes' => [
          'http-equiv' => 'Refresh',
          'content' => '5; URL=' . $url,
        ],
      ];

      return [
        '#type' => 'container',
        'html_head' => [[$meta_refresh, 'simplytest_tugboat_status_meta_refresh']],
        'page-contents' => [
          '#type' => 'markup',
          '#markup' => '<p>Forwarding you to the Tugboat instance</p>',
        ],
      ];

    }
    else {

      $this->logger->notice('State: ' . var_export($state, TRUE));

      // Redirect through a 'Refresh' meta tag if JavaScript is disabled.
      $current_path = Url::fromRoute('<current>', [], ['query' => ['no_js' => NULL]])
        ->toString();
      $meta_refresh = [
        '#prefix' => '<noscript>',
        '#suffix' => '</noscript>',
        '#tag' => 'meta',
        '#attributes' => [
          'http-equiv' => 'Refresh',
          // Keep refreshing the current page, but mark each refresh with no_js.
          'content' => '5; URL=' . url(current_path(), ['query' => ['no_js' => NULL]]),
          'content' => '5; URL=' . ltrim($current_path, '/'),
        ],
      ];

      // Return a progress bar and attach own javascript.
      return [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['simplytest-progress-bar'],
        ],
        '#attached' => [
          'html_head' => [[$meta_refresh, 'simplytest-progress-bar']],
          'js' => [
            [
              'data' => [
                'simplytest_tugboat' => [
                  'id' => $instance_id,
                ],
              ],
              'type' => 'setting',
            ],
          ],
        ],
        'progress-bar' => [
          '#theme' => 'progress_bar',
          '#percent' => $state['percent'],
          '#message' => $state['message'],
        ],
        'log' => [
          '#prefix' => '<div id="simplytest-log" class="log">',
          '#suffix' => '</div>',
          '#markup' => $state['log'],
          //'#children' => $this->t('number of items: ' . count($state['log'])),
          '#cache' => [
            'max-age' => 0,
          ],
        ],
      ];
    }
  }

  /**
   * Redirection page to final sandbox environment url.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   */
  public function goto($instance_id) {
    // Check for an established URL, forward if exists.
    $tugboat_url = $this->instanceManager->loadUrl($instance_id);
    if (!empty($tugboat_url) and $tugboat_url !== '') {
      return new TrustedRedirectResponse($tugboat_url);
    }

    // Check state.
    $state = $this->instanceManager->getStatusState($instance_id);
    if ($state['code'] === FALSE) {
      $this->messenger->addError($this->t('The requested submission is not available.'));
      return $this->redirect('<front>');
    }
    switch ($state['code']) {
      case InstanceManagerInterface::ENQUEUE:
      case InstanceManagerInterface::SPAWNED:
      case InstanceManagerInterface::PREPARE:
      case InstanceManagerInterface::DOWNLOAD:
      case InstanceManagerInterface::PATCHING:
      case InstanceManagerInterface::INSTALLING:
      case InstanceManagerInterface::FINALIZE:
        return $this->redirect('simplytest_tugboat.progress', ['instance_id' => $instance_id]);
        break;

      case InstanceManagerInterface::FINISHED:
        // @todo Will we get the same result as at the start of this function?
        $tugboat_url = $this->instanceManager->loadUrl($instance_id);
        return new TrustedRedirectResponse($tugboat_url);
        break;

      default:
        $this->message->addError($state['message']);
        return $this->redirect('<front>');
    }
  }

  /**
   * Callback for remote services to update the status of an instance.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   * @param int $status_code
   *   One of the constants from InstanceManagerInterface.
   */
  public function status($instance_id, $status_code) {
    $this->instanceManager->updateStatus($instance_id, $status_code);
    return new Response();
  }

  /**
   * New JSON output callback for current instance status.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   */
  public function instanceState($instance_id) {
    $state = $this->instanceManager->getStatusState($instance_id);
    $state['do_provision'] = $state['code'] === InstanceManagerInterface::ENQUEUE;
    return new JsonResponse($state);
  }

}
