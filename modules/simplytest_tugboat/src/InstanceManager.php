<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * InstanceManager service.
 */
class InstanceManager implements InstanceManagerInterface {
  use StringTranslationTrait;

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The Tugboat module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $tugboatSettings;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity.query service.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerAwareInterface
   */
  protected $entityQuery;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The logger channel for this module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface;
   */
  protected $logger;

  /**
   * The messenger service;
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The render service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * The project service.
   *
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected $projectFetcher;

  /**
   * Constructs an InstanceManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Symfony\Component\DependencyInjection\ContainerAwareInterface $entity_query
   *   The entity.query service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel for this module.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   * @param \Drupal\simplytest_projects\SimplytestProjectFetcher $project_fetcher
   *   `The project service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, Time $time, ContainerAwareInterface $entity_query, EntityTypeManager $entity_type_manager, LoggerChannelInterface $logger, MessengerInterface $messenger, ModuleHandlerInterface $module_handler, RendererInterface $renderer, TranslationInterface $string_translation, SimplytestProjectFetcher $project_fetcher) {
    $this->settings = $config_factory->get('simplytest_tugboat.settings');
    $this->tugboatSettings = $config_factory->get('tugboat.settings');
    $this->connection = $connection;
    $this->entityQuery = $entity_query;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->stringTranslation = $string_translation;
    $this->time = $time;
    $this->projectFetcher = $project_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getLog($instance_id) {
    $tugboat_repo = $this->tugboatSettings->get('tugboat_repository_id');

    $return_data = [];
    $error_string = '';

    $this->logger->notice('we are in the log function for ' . $instance_id);

    // Load the ID of the correct base preview ID.
    $preview_id = $this->loadPreviewId($instance_id, FALSE);

    // Run the tugboat command.
    $command = "log $preview_id";
    // @todo The _tugboat_execute() command is not yet defined.
    $return_status = _tugboat_execute($command, $return_data, $error_string);
    $log = [];
    foreach ($return_data as $log_entry) {
      switch ($log_entry['level']) {
      case 'error':
        $class = 'log-error';
        break;

      case 'stderr':
        $class = 'log-detail';
        break;

      default:
        $class = 'log-message';
      }
      $message = $log_entry['message'];
      // TODO - look at cleanup.
      //$message = preg_replace('/[^A-Za-z0-9 .]/u','',$message);
      $log[] = '<p class="' . $class . '">' . $message . "</p>";
    }
    return implode(' ', $log);
  }

  /**
   * {@inheritdoc}
   */
  public function loadPreviewId($context, $base = TRUE) {
    $branch_name = $base ? "base-$context" : $context;
    $this->logger->notice('Loading preview ID for ' . $branch_name); 
    $previews = [];
    $error_string = '';
    // @todo The _tugboat_execute() command is not yet defined.
    $return_status = _tugboat_execute("ls previews", $previews, $error_string);

    //$this->logger->notice('OUTPUT: ' . var_export($previews, TRUE));

    $max_timestamp = -1;
    $max_id = NULL;

    // Find the most recent preview ID for the base.
    if ($base) {
      $acceptable_states = [
        'ready',
        'suspended',
        'refreshing',
      ];
    }
    else {
      $acceptable_states = [
        'building',
        'pending',
        'new',
        'ready',
        'suspended',
        'refreshing',
      ];
    }
    $repo = $this->settings->get('github_repo');
    $ns = $this->settings->get('github_ns');
    $repo_filter = "https://github.com/$ns/$repo";
    //$this->logger->notice('Previews: ' . var_export($previews, TRUE));
    foreach ($previews as $num => $preview) {
      $ts = strtotime($preview['createdAt']);
      if ($preview['provider_label'] != $branch_name) {
        continue;
      }
      // if ($max_timestamp >= $ts) {
      //   continue;
      // }
      // if (!in_array($preview['state'], $acceptable_states)) {
      //   continue;
      // }
      if (strpos($preview['provider_link'], $repo_filter) === FALSE) {
        continue;
      }
      $max_id = $preview['id'];
      $max_timestamp = $ts;
    }

    // Log an error if ID not found
    if (empty($max_id)) {
      $message = $base
        ? "No base preview for: <em>$context</em>"
        : "No preview for: <em>$context</em>";
      $this->logger->error($message);
    }
    return $max_id;
  }

  /**
   * Get a list of all submission statuses with their label and messages.
   *
   * @param string $type
   *   (optional) One of 'running', 'terminated', or 'error'.
   *
   * @return array[]
   *   Return just the statuses of the supplied type, or all statuses if it is
   *   omitted. Each status is an array with the keys
   *   - code (int)
   *   - label (translated string)
   *   - message (string)
   *   - type (string)
   */
  protected function getStatusList($type = NULL) {
    $statuses = [
      // 100s - Running submission states.
      InstanceManagerInterface::ENQUEUE => [
        'label' => $this->t('Enqueued'),
        'message' => $this->t('Your submission is enqueued.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::SPAWNED => [
        'label' => $this->t('Spawned'),
        'message' => $this->t('The submission was spawned.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::PREPARE => [
        'label' => $this->t('Preparing'),
        'message' => $this->t('The environment is being prepared.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::DOWNLOAD => [
        'label' => $this->t('Downloading'),
        'message' => $this->t('Fetching and downloading dependencies.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::PATCHING => [
        'label' => $this->t('Patching'),
        'message' => $this->t('Downloading and applying patches.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::INSTALLING => [
        'label' => $this->t('Installing'),
        'message' => $this->t('Setup and installation.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::FINALIZE => [
        'label' => $this->t('Finalizing'),
        'message' => $this->t('Final polish.'),
        'type' => 'running',
      ],
      InstanceManagerInterface::FINISHED => [
        'label' => $this->t('Finished'),
        'message' => $this->t('Finished!'),
        'type' => 'running',
      ],

      // 200s - Terminated submission states.
      InstanceManagerInterface::TERMINATED => [
        'label' => $this->t('Terminated'),
        'message' => $this->t('This submission is already terminated.'),
        'type' => 'terminated',
      ],
      InstanceManagerInterface::ABORTED => [
        'label' => $this->t('Aborted'),
        'message' => $this->t('The requested submission was aborted.'),
        'type' => 'terminated',
      ],
      InstanceManagerInterface::FAILED => [
        'label' => $this->t('Failed'),
        'message' => $this->t('The requested submission failed.'),
        'type' => 'terminated',
      ],

      // 300s - Failure submission states.
      InstanceManagerInterface::ERROR_SERVER => [
        'label' => $this->t('Error server'),
        'message' => $this->t('An error occurred while launching the submission.'),
        'type' => 'error',
      ],
      InstanceManagerInterface::ERROR_SPAWNED => [
        'label' => $this->t('Error spawning'),
        'message' => $this->t('An error occurred while spawning the environment.'),
        'type' => 'error',
      ],
      InstanceManagerInterface::ERROR_PREPARE => [
        'label' => $this->t('Error prepare'),
        'message' => $this->t('An error occurred while preparing the environment.'),
        'type' => 'error',
      ],
      InstanceManagerInterface::ERROR_DOWNLOAD => [
        'label' => $this->t('Error download'),
        'message' => $this->t('An error occurred while downloading dependencies.'),
        'type' => 'error',
      ],
      InstanceManagerInterface::ERROR_PATCHING => [
        'label' => $this->t('Error patching'),
        'message' => $this->t('An error occurred while patching the project.'),
        'type' => 'error',
      ],
      InstanceManagerInterface::ERROR_INSTALLING => [
        'label' => $this->t('Error installing'),
        'message' => $this->t('An error occurred while installing the environment.'),
        'type' => 'error',
      ],
      InstanceManagerInterface::ERROR_FINALIZE => [
        'label' => $this->t('Error finalizing'),
        'message' => $this->t('An error occurred while finalizing the environment.'),
        'type' => 'error',
      ],
    ];

    foreach ($statuses as $code => $status) {
      $statuses[$code]['code'] = $code;
    }

    if ($type) {
      $statuses = array_filter($statuses, function (array $status) use ($type) {
        return $status['type'] == $type;
      });
    }

    return $statuses;
  }

  /**
   * Get a specific status by its code.
   *
   * @param int $status_code
   *   One of the status code constants from InstanceManagerInterface.
   *
   * @return array|bool
   *   FALSE if $status_code is not one of the defined constants. Otherwise an
   *   array as described in ::getStatusList().
   */
  protected function getStatus($status_code) {
    $statuses = getStatusList();
    return $statuses[$status_code] ?? FALSE;
  }

  /**
   * Get all statuses for the given instance.
   *
   * @param string $instance_id
   *   The primary identifier for the instance.
   *
   * @return \Drupal\simplytest_tugboat\Entity\StmTugboatInstanceStatus[]
   *   An array of Status entities for the given instance.
   */
  protected function getInstanceStatuses($instance_id) {
    $entity_ids = $this->entityQuery->get('stm_tugboat_instance_status')
      ->condition('instance_id', $instance_id)
      ->sort('created')
      ->execute();

    return $this->entityTypeManager->getStorage('stm_tugboat_instance_status')
      ->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadUrl($instance_id) {
    $entity_ids = $this->entityQuery->get('stm_tugboat_instanceurl')
      ->condition('instance_id', $instance_id)
      ->range(0,1)
      ->execute();

    if (empty($entity_ids)) {
      return '';
    }

    return $this->entityTypeManager->getStorage('stm_tugboat_instanceurl')
      ->load(reset($entity_ids))
      ->tugboat_url->value;
  }

  /**
   * {@inheritdoc}
   */
  public function updateUrl($instance_id, $tugboat_url) {
    // Check this is a valid URL.
    if (!UrlHelper::isValid($tugboat_url, TRUE)) {
      $this->logger->notice('Invalid URL sent to tugboat url update');
      return;
    }

    // Check to make sure URL not already set.
    $entity_ids = $this->entityQuery->get('stm_tugboat_instanceurl')
      ->condition('instance_id', $instance_id)
      ->exists('tugboat_url')
      ->execute();
    if ($entity_ids) {
      return;
    }

    // @todo Be more DRY.
    $entity_ids = $this->entityQuery->get('stm_tugboat_instanceurl')
      ->condition('instance_id', $instance_id)
      ->execute();
    if (empty($entity_ids)) {
      return;
    }

    try {
      $this->entityTypeManager->getStorage('stm_tugboat_instanceurl')
        ->load(reset($entity_ids))
        ->set('tugboat_url', $tugboat_url)
        ->save();
    }
    catch (\Exception $exception) {
      watchdog_exception('simplytest_tugboat', $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadContext($instance_id) {
    $entity_ids = $this->entityQuery->get('stm_tugboat_instanceurl')
      ->condition('instance_id', $instance_id)
      ->range(0,1)
      ->execute();

    if (empty($entity_ids)) {
      return '';
    }

    return $this->entityTypeManager->getStorage('stm_tugboat_instanceurl')
      ->load(reset($entity_ids))
      ->context->value;
  }

  /**
   * {@inheritdoc}
   */
  public function createWithContext($instance_id, $context){
    // Check to make sure URL not already set.
    $entity_ids = $this->entityQuery->get('stm_tugboat_instanceurl')
      ->condition('instance_id', $instance_id)
      ->execute();
    if ($entity_ids) {
      return;
    }

    try {
      $this->entityTypeManager->getStorage('stm_tugboat_instanceurl')
        ->create([
          'instance_id' => $instance_id,
          'context' => $context,
        ])
        ->save();
    }
    catch (\Exception $exception) {
      watchdog_exception('simplytest_tugboat', $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateStatus($instance_id, $status) {
    $status = (int) $status;
    $status_valid = $this->getStatus($status);

    if (!$status_valid) {
      return;
    }

    $entity_ids = $this->entityQuery->get('stm_tugboat_instance_status')
      ->condition('instance_id', $instance_id)
      ->condition('instance_status', $status)
      ->execute();

    // Don't set the same status twice for an instance.
    if ($entity_ids) {
      return;
    }

    try {
      $this->entityTypeManager->getStorage('stm_tugboat_instance_status')
        ->create([
          'created' => getRequestTime(),
          'instance_id' => $instance_id,
          // 'tugboat_url' => 'FIXME',
          'instance_status' => $status,
        ])
        ->save();
    }
    catch (\Exception $exception) {
      watchdog_exception('simplytest_tugboat', $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusState($instance_id) {
    $result = [
      'code' => 0,
      'percent' => '0',
      'message' => $this->t('No instance status found...'),
      'log' => [],
    ];

    $statuses = $this->getInstanceStatuses($instance_id);
    if (!empty($statuses)) {
      $instance_status = array_pop($statuses);
      $status = $this->getStatus($instance_status->instance_status->value);

      if ($status) {
        $result['code'] = $status['code'];
        $result['percent'] = runningPercentage($status['code']);
        $result['message'] = $status['message'];
        $result['log'] = _simplytest_tugboat_get_log($instance_id);
      }
    }

    return $result;
  }

  /**
   * Get the progress percentage for a running instance.
   *
   * @param int $status_code
   *   One of the status code constants from InstanceManagerInterface.
   *
   * @return string
   *   A string like "25" representing the fraction of completed steps.
   */
  protected function runningPercentage($status_code) {
    $running_status_codes = array_keys($this->getStatusList('running'));
    $total = count($running_status_codes);
    $current = array_search($status_code, $running_status_codes) ?: $total;

    return $this->percentage($total, $current + 1);
  }

  /**
   * Format the percent completion of a progress bar.
   *
   * @param int $total
   *   The total number of operations.
   * @param int $current
   *   The number of the current operation. This may be a floating point number
   *   rather than an integer in the case of a multi-step operation that is not
   *   yet complete. In that case, the fractional part of $current represents the
   *   fraction of the operation that has been completed.
   *
   * @return string
   *   The properly formatted percentage, as a string. We output percentages
   *   using the correct number of decimal places so that we never print "100%"
   *   until we are finished, but we also never print more decimal places than
   *   are meaningful.
   *
   * @see _batch_api_percentage()
   */
  protected function percentage($total, $current) {
    if (!$total || $total == $current) {
      // If $total doesn't evaluate as true or is equal to the current set, then
      // we're finished, and we can return "100".
      return "100";
    }

    // We add a new digit at 200, 2000, etc. (since, for example, 199/200
    // would round up to 100% if we didn't).
    $decimal_places = max(0, floor(log10($total / 2.0)) - 1);
    do {
      // Calculate the percentage to the specified number of decimal places.
      $percentage = sprintf('%01.' . $decimal_places . 'f', round($current / $total * 100, $decimal_places));
      // When $current is an integer, the above calculation will always be
      // correct. However, if $current is a floating point number (in the case
      // of a multi-step batch operation that is not yet complete), $percentage
      // may be erroneously rounded up to 100%. To prevent that, we add one
      // more decimal place and try again.
      $decimal_places++;
    } while ($percentage == '100');
    return $percentage;
  }

  /**
   * {@inheritdoc}
   */
  public function launchInstance($submission) {
    // Get a new instance id.
    $ns = $this->settings->get('github_ns');
    $repo = $this->settings->get('github_repo');
    $ref = _simplytest_tugboat_generate_id();

    if (!$ref) {
      $this->messenger->addMessage($this->t('Error communicating with the GitHub API. Please try again later.'));
      $this->logger->notice('Error generating ID with the GitHub API');
      return;
    }

    simplytest_tugboat_status_update($ref, InstanceManagerInterface::ENQUEUE);

    // Load a client.
    $client = new \Github\Client();
    $client->authenticate($this->settings->get('github_api'), '', Github\Client::AUTH_HTTP_TOKEN);

    // Get most recent commit SHA on master.
    $commits = $client->api('repo')->commits()->all($ns, $repo, ['sha' => 'master']);
    $commit = reset($commits);

    // Make a new branch in github.
    $referenceData = ['ref' => "refs/heads/$ref", 'sha' => $commit['sha']];
    $client->api('gitData')->references()->create($ns, $repo, $referenceData);

    // Get relevant Drupal core version.
    $core_versions = $this->projectFetcher->fetchVersions('drupal');
    usort($core_versions['tags'], 'version_compare');

    // Set a default project and version, if none exist.
    if (empty($submission['project'])) {
      $submission['project'] = 'drupal';
      $submission['version'] = end($core_versions['tags']);
    }

    // Let's generate contents of the .tugboat/config.yml file.
    $split_versions = explode('-', $submission['version']);
    $major_version = array_shift($split_versions);
    $major_version = substr($major_version, 0, 1);
    $project_version = implode('-', $split_versions);

    // Check for dev release for 8 only (composer).
    if ($submission['project'] != 'drupal' && substr($project_version, -1) == 'x' && $major_version == '8') {
      $project_version .= '-dev';
    }

    // Filter by major version.
    foreach ($core_versions['tags'] as &$tag) {
      if ($tag[0] != $major_version) {
        unset($tag);
      }
    }

    // Get the latest.
    $core_release = end($core_versions['tags']);

    // Clean it up.
    if (substr($core_release, -3) === '^{}') {
      $core_release = substr($core_release, 0, -3);
    }

    // Send parameters.
    $parameters  = [
      'perform_install' => !$submission['bypass_install'],
      'drupal_core_version' => $core_release,
      'project_type' => $this->projectFetcher->fetchProject($submission['project'])['type'],
      'project_version' => ($submission['project'] == 'drupal') ? $submission['version'] : $project_version,
      'project' => $submission['project'],
      'patches' => $submission['patches'],
      'additionals' => $submission['additionals'],
      'instance_id' => $ref,
      'hash' => Crypt::randomBytesBase64(),
      'major_version' => $major_version,
      // Do not use Url::fromRoute() because this is a partial path.
      'status_endpoint' => Url::fromUserInput("/tugboat/update/status/$ref")->toString(),
    ];

    // Make the context and write the record.
    $context = "drupal$major_version";

    // Check for one click demos.
    if (module_exists('simplytest_ocd') && !empty($submission['stm_one_click_demo'])) {
      // Temporarily set the major version to 8.x tags only.
      $core_versions = $this->projectFetcher->fetchVersions('drupal');
      usort($core_versions['tags'], 'version_compare');
      while ($core_release[0] == '9' && !empty($core_release)){
        $core_release = array_pop($core_versions['tags']);
      }
      $parameters['drupal_core_version'] = $core_release;

      // Clean it up.
      if (substr($parameters['drupal_core_version'], -3) === '^{}') {
        $parameters['drupal_core_version'] = substr($parameters['drupal_core_version'], 0, -3);
      }

      // Run OCD specific logic.
      $ocds = $this->moduleHandler->invokeAll('simplytest_ocd');
      if (count($ocds)) {
        // Add a button for each one.
        foreach ($ocds as $ocd) {
          $button_id = $ocd['ocd_id'];
          if ($submission['stm_one_click_demo']['#name'] == $button_id) {
            $theme_key = $ocd['theme_key'];
            $elements = [
              '#theme' => 'simplytest_tugboat_config_' . $theme_key . '_yml',
              '#parameters' => $parameters,
            ];
            $config_yml_contents = (string) $this->renderer->renderPlain($elements);
            $context = $button_id;
          }
        }
      }
      // Standard form submit.
    }
    else {
      $elements = [
        '#theme' => 'simplytest_tugboat_config_' . $major_version . '_yml',
        '#parameters' => $parameters,
      ];
      $config_yml_contents = (string) $this->renderer->renderPlain($elements);
    }

    // Save context.
    $this->createWithContext($ref, $context);

    // Update the file.
    $committer = [
      'name' => $this->settings->get('github_username'),
      'email' => $this->settings->get('github_email'),
    ];
    $commitMessage = "Config for instance $ref";
    $path = '.tugboat/config.yml';
    $oldFile = $client->api('repo')->contents()->show($ns, $repo, $path, $ref);
    $fileInfo = $client->api('repo')->contents()
      ->update($ns, $repo, $path, $config_yml_contents, $commitMessage, $oldFile['sha'], $ref, $committer);

    // Redirect to progress page.
    // @todo: skip this or not?
    // simplytest_tugboat_status_goto($ref);
  }

  /**
   * This is a callback to get a unique ID for the instance.
   *
   * @param int $tries
   *   (optional, used internally) The number of failed attempts. Give up after
   *   four tries.
   */
  protected function generateId($tries = 0) {
    $prefix = $this->settings->get('prefix');
    $ns = $this->settings->get('github_ns');
    $repo = $this->settings->get('github_repo');

    $id = uniqid($prefix);

    // Check the git branch for collision.
    $client = new \Github\Client();
    $client->authenticate($this->settings->get('github_api'), '', \Github\Client::AUTH_HTTP_TOKEN);
    $branches = $client->api('repo')->branches($ns, $repo);
    if (in_array($id, $branches) and $tries <= 3) {
      $tries++;
      $id = $this->generateId($tries);
    }

    // Something went wrong.
    if ($tries == 4) {
      return FALSE;
    }
    return $id;
  }

}
