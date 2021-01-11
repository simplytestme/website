<?php

namespace Drupal\simplytest_tugboat;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
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
    // Get relevant Drupal core version.
    $core_versions = $this->projectFetcher->fetchVersions('drupal');
    usort($core_versions['tags'], 'version_compare');

    // Set a default project and version, if none exist.
    if (empty($submission['project'])) {
      $submission['project'] = [
        'shortname' => 'drupal',
        'version' => end($core_versions['tags'])
      ];
    }
    $project_version = $submission['project']['version'];
    // Let's generate contents of the .tugboat/config.yml file.
    // @todo needs to handle semver.
    if (strpos($submission['project']['version'], '.x-') === 1) {
      $major_version = $submission['project']['version'][0];
      [, $project_version] = explode('-', $submission['project']['version'], 2);
    }
    // Default to D9 for semantic versions.
    elseif (is_numeric($submission['project']['version'])) {
      $major_version = '9';
    }
    // Who knows how we got here, default to 9.
    else {
      $major_version = '9';
    }

    // Check for dev release for 8 only (composer).
    if ($submission['project']['shortname'] !== 'drupal' && substr($project_version, -1) == 'x' && $major_version >= '7') {
      $project_version .= '-dev';
    }

    $core_version_candidates = array_filter($core_versions['tags'], static function($tag) use ($major_version) {
      return $tag[0] === $major_version;
    });

    // Get the latest.
    $core_release = end($core_version_candidates);

    // Clean it up.
    if (substr($core_release, -3) === '^{}') {
      $core_release = substr($core_release, 0, -3);
    }

    // Send parameters.
    $parameters  = [
      'perform_install' => !$submission['manualInstall'],
      // @todo why aren't we using $submission['drupalVersion']? security?
      'drupal_core_version' => $core_release,
      // @todo we have $submission['project']['type] but it is human-readable not machine name.
      'project_type' => $this->projectFetcher->fetchProject($submission['project']['shortname'])['type'],
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

    // Check for one click demos.
    if ($this->moduleHandler->moduleExists('simplytest_ocd') && !empty($submission['stm_one_click_demo'])) {
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
            $context = $button_id;
            $config = $this->previewConfigGenerator->oneClickDemo($ocd['theme_key'], $parameters);
          }
        }
      }
      // Standard form submit.
    }
    else {
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
