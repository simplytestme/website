<?php

namespace Drupal\simplytest_ocd\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Render\Markup;
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
    $templates = array_values(array_filter(array_map(
      $this->templateCard(...),
      $this->definitionsIn('site_template'),
    )));
    usort($templates, static fn(array $a, array $b) => strcasecmp($a['name'], $b['name']));

    $response = new CacheableJsonResponse($templates);
    $response->getCacheableMetadata()->addCacheableDependency($this->manager);
    return $response;
  }

  /**
   * The page a site template's permalink opens, such as /template/byte.
   *
   * This is for other sites to link to, like the Drupal CMS installer offering
   * a demo of each template. Loading the page does not launch anything: link
   * previews, crawlers and prefetching all make GET requests, and every launch
   * is a Tugboat build. The visitor presses Launch, which posts to ::launch().
   *
   * @return array<string, mixed>
   */
  public function siteTemplate(string $machine_name): array {
    $build = [
      'mount' => [
        '#markup' => Markup::create('<div class="simplytest-react-component" id="site_template_mount"></div>'),
        '#attached' => [
          'library' => [
            'simplytest_theme/launcher',
          ],
          'drupalSettings' => [
            'siteTemplate' => $this->siteTemplateCard($machine_name),
          ],
        ],
      ],
    ];
    CacheableMetadata::createFromObject($this->manager)->applyTo($build);
    return $build;
  }

  /**
   * The title for ::siteTemplate().
   */
  public function siteTemplateTitle(string $machine_name): string {
    return $this->siteTemplateCard($machine_name)['name'];
  }

  /**
   * The card for one site template, by its machine name.
   *
   * @return array{id: string, name: string, description: string, screenshot: string|null, creator: string|null, links: list<array{text: string, url: string}>}
   *
   * @throws \Drupal\Core\Http\Exception\CacheableNotFoundHttpException
   *   When the curated list has no such template. The 404 varies with the
   *   plugin definitions, so it clears once the next import adds the template.
   */
  private function siteTemplateCard(string $machine_name): array {
    $definition = $this->definitionsIn('site_template')["site_template:$machine_name"] ?? NULL;
    $card = $definition === NULL ? NULL : $this->templateCard($definition);
    if ($card === NULL) {
      throw new CacheableNotFoundHttpException(
        CacheableMetadata::createFromObject($this->manager),
        "$machine_name is not a site template",
      );
    }
    return $card;
  }

  /**
   * What the front end shows for a site template.
   *
   * @param array<string, mixed> $definition
   *   The template's plugin definition.
   *
   * @return array{id: string, name: string, description: string, screenshot: string|null, creator: string|null, links: list<array{text: string, url: string}>}|null
   *   The card, or NULL when the definition carries no template.
   */
  private function templateCard(array $definition): ?array {
    $template = $definition['template'] ?? NULL;
    if (!is_array($template)) {
      return NULL;
    }
    return [
      'id' => $definition['id'],
      'name' => $template['name'],
      'description' => $template['description'],
      'screenshot' => $template['screenshot'],
      'creator' => $template['creator'],
      'links' => $template['links'],
    ];
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
