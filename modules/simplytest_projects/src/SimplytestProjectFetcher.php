<?php

namespace Drupal\simplytest_projects;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\simplytest_projects\Entity\SimplytestProject;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;

/**
 * Class SimplytestProjectFetcher
 *
 * @package Drupal\simplytest_projects
 */
class SimplytestProjectFetcher implements ContainerInjectionInterface {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  public $log;

  /**
   * @var \GuzzleHttp\Client
   */
  public $httpClient;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  public $config;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  public $entityTypeManager;

  /**
   * SimplytestProjectFetcher constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger_factory
   * @param \Drupal\Core\Config\ConfigFactoryInterface
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public function __construct(
    Client $http_client,
    LoggerChannelFactory $logger_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager)
  {
    $this->httpClient = $http_client;
    $this->log = $logger_factory->get('simplytest_projects');
    $this->config = $config_factory->get('simplytest_projects.settings');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Try to fetch project data from drupal.org's JSON / RESTWS API.
   *
   * @param $shortname
   *
   * @return array|bool
   * @throws \Exception
   */
  public function fetchProject($shortname) {
    $result = $this->httpClient->get(DrupalUrls::ORG_API . 'node.json?field_project_machine_name=' . urlencode($shortname));
    if ($result->getStatusCode() != 200 || empty($result->getBody())) {
      $this->log->warning('Failed to fetch initial data for %project (Request).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Try to parse the received JSON.
    $data = Json::decode($result->getBody());
    if ($data === null) {
      $this->log->warning('Failed to parse initial data for %project (json decode).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Did we find the project we searched for?
    if (count($data['list']) === 0 || !isset($data['list'][0])) {
      $this->log->warning('Failed to get initial data for %project (empty search result).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    $project_data = $data['list'][0];

    // Determine the type of this project.
    $project_type = strtolower(trim($project_data['field_project_type']));
    switch ($project_type) {

      case 'full':
        $sandbox = FALSE;
        break;

      case 'sandbox':
        $sandbox = TRUE;
        break;

      default:
        $this->log->warning('Failed to get initial data for %project (invalid project type "@type").',[
          '%project' => $shortname,
          '@type' => $project_type,
        ]);
        return FALSE;
    }

    // Determine project title.
    if (!isset($project_data['title'])) {
      $this->log->warning('Failed to get initial data for %project (no project title).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    $title = $project_data['title'];

    // Determine the project type term.
    if (!isset($project_data['type'])) {
      $this->log->warning('Failed to get initial data for %project (no project type).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    $type_term = $project_data['type'];

    // Find out the type by term.
    $type = ProjectTypes::getProjectType($type_term);
    if ($type === FALSE) {
      // Unknown type, error.
      $this->log->warning('Failed to get initial data for %project (Determine type for term "@term").', [
        '%project' => $shortname,
        '@term' => $type_term,
      ]);
      return FALSE;
    }

    // Get author name from project url.
    if ($sandbox) {
      if (!isset($project_data['url'])) {
        $this->log->warning('Failed to scrap user name from "%url".', [
          '%url' => $project_data['url'],
        ]);
        return FALSE;
      }
      $url_parts = explode('/', $project_data['url']);
      $creator = $url_parts[4];
    }
    else {
      // Creator is irrelevant for full projects; also the username is not in the URL of them.
      $creator = NULL;
    }

    // Build an array of all the new project data.
    $data = array(
      'title' => $title,
      'shortname' => $shortname,
      'sandbox' => (int) $sandbox,
      'type' => $type,
      'creator' => $creator,
    );

    $this->log->notice('Fetch initial data for %project.', [
      '%project' => $shortname,
    ]);

    // Now save the information about this project to database.
    $project = SimplytestProject::create($data);
    $project->save();

    return $data;
  }

  /**
   * Fetches, saves and returns all available versions for a project.
   *
   * @param string $shortname
   *  The project's shortname to fetch available versions for.
   *
   * @return array|FALSE
   *  An associative array containing:
   *   - tags: Existing tags of the project.
   *   - heads: Existing heads of the project.
   */
  public function fetchVersions($shortname) {
    // Check whether project is known in database.
    $project_ids = $this->entityTypeManager
      ->getStorage('simplytest_project')
      ->getQuery('AND')
      ->condition('shortname', $shortname)
      ->execute();

    $project = SimplytestProject::load(reset($project_ids));

    // Fetch tags by request.
    $result = $this->httpClient->get($project->getGitUrl() . '/refs/tags');
    if ($result->getStatusCode() != 200 || empty($result->getBody())) {
      $this->log->warning('Failed to fetch version data for %project (Requested tags).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    // Try to match out a list of tags of the raw HTML.
    preg_match_all('!<tr><td><a href=\'/.*/tag/[^\']*\'>([^<]*)</a></td>!', $result->getBody(), $tags);
    if(!isset($tags[1])) {
      $this->log->warning('Failed to fetch version data for %project (Fetched tags).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Fetch branches by request.
    $result = $this->httpClient->get($project->getGitUrl() . '/refs/heads');
    if ($result->getStatusCode() != 200 || empty($result->getBody())) {
      $this->log->warning('Failed to fetch version data for %project (Requested heads).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }
    // Try to match out a list of tags of the raw HTML.
    preg_match_all('!<tr><td><a href=\'/.*/log/[^\']*\'>([^<]*)</a></td>!', $result->getBody(), $heads);
    if(!isset($heads[1])) {
      $this->log->warning('Failed to fetch version data for %project (Fetch heads).', [
        '%project' => $shortname,
      ]);
      return FALSE;
    }

    // Blacklist filters.
    $blacklisted_versions = $this->config->get('blacklisted_versions');
    foreach ($blacklisted_versions as $blacklisted) {
      foreach ($tags[1] as $key => $tag) {
        if (preg_match('!' . $blacklisted . '!', $tag)) {
          unset($tags[1][$key]);
        }
      }
      foreach ($heads[1] as $key => $head) {
        if (preg_match('!' . $blacklisted . '!', $head)) {
          unset($heads[1][$key]);
        }
      }
    }

    // Now save the information about this project to database.
    $project->setVersions($tags[1], $heads[1]);
    $project->save();

    $this->log->notice('Fetched version data for %project.', [
      '%project' => $shortname,
    ]);

    return $project->getVersions();
  }

}
