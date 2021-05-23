<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Drupal\tugboat\TugboatClient;

/**
 * InstanceManager service.
 */
class InstanceManager implements InstanceManagerInterface {
  use StringTranslationTrait;

  /**
   * The Tugboat module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $tugboatSettings;

  /**
   * The logger channel for this module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface;
   */
  protected $logger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The project service.
   *
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected $projectFetcher;

  /**
   * The Tugboat client.
   * @var \Drupal\tugboat\TugboatClient
   */
  protected $tugboatClient;

  /**
   * The preview config generator.
   *
   * @var \Drupal\simplytest_tugboat\PreviewConfigGenerator
   */
  protected $previewConfigGenerator;

  /**
   * Constructs an InstanceManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel for this module.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\simplytest_projects\SimplytestProjectFetcher $project_fetcher
   *   The project service.
   * @param \Drupal\tugboat\TugboatClient $tugboat_client
   *   The Tugboat client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelInterface $logger, ModuleHandlerInterface $module_handler, TranslationInterface $string_translation, SimplytestProjectFetcher $project_fetcher, TugboatClient $tugboat_client, PreviewConfigGenerator $preview_config_generator) {
    $this->tugboatSettings = $config_factory->get('tugboat.settings');
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
    $this->projectFetcher = $project_fetcher;
    $this->tugboatClient = $tugboat_client;
    $this->previewConfigGenerator = $preview_config_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function loadPreviewId($context, $base = TRUE) {
    $branch_name = $base ? "base-$context" : $context;
    $repository_id = $this->tugboatSettings->get('repository_id');
    $response = $this->tugboatClient->requestWithApiKey('GET', "repos/{$repository_id}/previews");
    $previews = Json::decode((string) $response->getBody());
    $max_id = NULL;

    // Find the most recent preview ID for the base.
    foreach ($previews as $preview) {
      if ($preview['provider_label'] !== $branch_name) {
        continue;
      }
      $max_id = $preview['id'];
      break;
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
   * {@inheritdoc}
   *
   * @todo accept \Drupal\simplytest_launch\Plugin\DataType\InstanceLaunch
   * @todo decide if data types should be refactored into here.
   */
  public function launchInstance($submission) {
    // @todo move into its own method or OCD controller directly?
    // Check for one click demos.
    if (!empty($submission['oneclickdemo']) && $this->moduleHandler->moduleExists('simplytest_ocd')) {
      $ocd_manager = \Drupal::service('plugin.manager.oneclickdemo');
      assert($ocd_manager instanceof OneClickDemoPluginManager);
      $ocd = $ocd_manager->getDefinition($submission['oneclickdemo']);

      $context = $ocd['base_preview_name'];
      // @todo Should one-click-demos _really_ have parameters? they're one click.
      $config = $this->previewConfigGenerator->oneClickDemo($submission['oneclickdemo'], []);
    }
    else {
      $project_version = $submission['project']['version'];
      $major_version = $submission['drupalVersion'][0];

      // Send parameters.
      $parameters  = [
        'perform_install' => !$submission['manualInstall'],
        'install_profile' => $submission['installProfile'],
        'drupal_core_version' => $submission['drupalVersion'],
        'project_type' => $submission['project']['type'],
        'project_version' => $project_version,
        'project' => $submission['project']['shortname'],
        'patches' => array_filter($submission['project']['patches'] ?? []),
        // @todo do we need to map the versions at all?
        'additionals' => $submission['additionalProjects'] ?? [],
        'instance_id' => Crypt::randomBytesBase64(),
        'hash' => Crypt::randomBytesBase64(),
        'major_version' => $major_version,
      ];

      // Make the context and write the record.
      $context = "drupal$major_version";
      $config = $this->previewConfigGenerator->generate($parameters);
    }

    $base_preview_id = $this->loadPreviewId($context, TRUE);
    $tugboat_request = $this->tugboatClient->requestWithApiKey('POST', 'previews', [
      'ref' => $this->tugboatSettings->get('repository_base') ?: 'master',
      'config' => $config,
      'name' => 'simplytest',
      'repo' => $this->tugboatSettings->get('repository_id'),
      'base' => $base_preview_id,
    ]);
    $response = Json::decode((string) $tugboat_request->getBody());
    return [
      'meta' => [
        'headers' => $tugboat_request->getHeaders(),
      ],
      'tugboat' => [
        'preview_id' => $response['preview'],
        'job_id' => $response['job'],
        'job_url' => $tugboat_request->getHeader('Content-Location'),
      ],
    ];
  }

}
