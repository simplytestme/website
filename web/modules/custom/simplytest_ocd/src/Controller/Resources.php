<?php

namespace Drupal\simplytest_ocd\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\simplytest_ocd\OneClickDemoPluginManager;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Returns responses for simplytest ocd module routes.
 */
class Resources implements ContainerInjectionInterface {

  /**
   * The simplytest_ocd plugin manager.
   *
   * @var \Drupal\simplytest_ocd\OneClickDemoPluginManager
   */
  protected $manager;

  /**
   * Simplytest Project Fetcher Service.
   *
   * @var \Drupal\simplytest_tugboat\InstanceManagerInterface
   */
  protected $instanceManager;

  /**
   * Constructs a new SimplyTestOCD object.
   *
   * @param \Drupal\simplytest_ocd\OneClickDemoPluginManager $manager
   *   The simplytest_ocd plugin manager.
   * @param \Drupal\simplytest_tugboat\InstanceManagerInterface
   *   The simplytest tugboat instance manager service.
   */
  public function __construct(OneClickDemoPluginManager $manager, InstanceManagerInterface $instance_manager) {
    $this->manager = $manager;
    $this->instanceManager = $instance_manager;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.oneclickdemo'),
      $container->get('simplytest_tugboat.instance_manager')
    );
  }

  public function launch($oneclickdemo_id) {
    if (!$this->manager->hasDefinition($oneclickdemo_id)) {
      throw new NotFoundHttpException("$oneclickdemo_id is not a valid option");
    }

    $submission = [
      'oneclickdemo' => $oneclickdemo_id,
      'manualInstall' => FALSE,
    ];
    try {
      // @todo we need a launchOneClickDemo method?
      $instance = $this->instanceManager->launchInstance($submission);
    } catch (\Throwable $e) {
      throw new ServiceUnavailableHttpException(null, $e->getMessage(), $e);
    }
    return new JsonResponse(
      [
        'status' => 'OK',
        'progress' => Url::fromRoute('simplytest_tugboat.progress', [
          'instance_id' => $instance['tugboat']['preview_id'],
          'job_id' => $instance['tugboat']['job_id'],
        ])->setAbsolute()->toString()
      ] + $instance,
    );
  }

  /**
   * The demo tiles for the home page.
   */
  public function info() {
    $ocds = array_values(array_map(static fn(array $definition) => [
      'id' => $definition['id'],
      'title' => $definition['title'],
      'base_preview_name' => $definition['base_preview_name'],
      'description' => $definition['description'] ?? '',
      'weight' => $definition['weight'] ?? 0,
      'recommended' => $definition['recommended'] ?? FALSE,
    ], $this->definitionsIn('demo')));
    usort($ocds, static fn(array $a, array $b) => $a['weight'] <=> $b['weight']);

    $response = new CacheableJsonResponse($ocds);
    $response->getCacheableMetadata()->addCacheableDependency($this->manager);
    return $response;
  }

  /**
   * The site template cards for the template picker.
   *
   * Separate from ::info() because a template is not a demo tile: it carries a
   * screenshot, a creator and links, and it belongs behind the picker rather
   * than on the home page. Both launch through ::launch().
   */
  public function siteTemplates(): CacheableJsonResponse {
    $templates = [];
    foreach ($this->definitionsIn('site_template') as $definition) {
      $template = $definition['template'] ?? NULL;
      if (!is_array($template)) {
        continue;
      }
      $templates[] = [
        'id' => $definition['id'],
        'name' => $template['name'],
        'description' => $template['description'],
        'screenshot' => $template['screenshot'],
        'creator' => $template['creator'],
        'links' => $template['links'],
      ];
    }
    usort($templates, static fn(array $a, array $b) => strcasecmp($a['name'], $b['name']));

    $response = new CacheableJsonResponse($templates);
    $response->getCacheableMetadata()->addCacheableDependency($this->manager);
    return $response;
  }

  /**
   * The plugin definitions belonging to one group.
   *
   * @return array<string, array<string, mixed>>
   */
  private function definitionsIn(string $group): array {
    return array_filter(
      $this->manager->getDefinitions(),
      static fn(array $definition): bool => ($definition['group'] ?? 'demo') === $group,
    );
  }

}
