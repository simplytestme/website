<?php

namespace Drupal\simplytest_launch\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplytest_projects\SimplytestProjectFetcher;
use Drupal\simplytest_tugboat\InstanceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for config module routes.
 */
class SimplyTestLaunch implements ContainerInjectionInterface {

  /**
   * Simplytest Project Fetcher Service.
   *
   * @var \Drupal\simplytest_projects\SimplytestProjectFetcher
   */
  protected $simplytestProjectFetcher;

  /**
   * Simplytest Project Fetcher Service.
   *
   * @var \Drupal\simplytest_tugboat\InstanceManagerInterface
   */
  protected $instanceManager;

  /**
   * Constructs a new ViewEditForm object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack object.
   * @param \Drupal\simplytest_projects\SimplytestProjectFetcher
   *   The simplytest fetcher service.
   * @param \Drupal\simplytest_tugboat\InstanceManagerInterface
   *   The simplytest tugboat instance manager service.
   */
  public function __construct(SimplytestProjectFetcher $simplytest_project_fetcher, InstanceManagerInterface $instance_manager) {
    $this->simplytestProjectFetcher = $simplytest_project_fetcher;
    $this->instanceManager = $instance_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplytest_projects.fetcher'),
      $container->get('simplytest_tugboat.instance_manager')
    );
  }

  public function configure(Request $request) {
    $text = [
      'problems' => [
        'title' => new TranslatableMarkup('Encountered a problem?'),
        'text' => new TranslatableMarkup('Vestibulum id ligula porta felis euismod semper. Nulla vitae elit libero, a pharetra augue.'),
        'link' => [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Report a problem'),
          '#url' => 'https://www.drupal.org/project/issues/simplytest',
        ]
      ],
      'future' => [
        'title' => new TranslatableMarkup('Future of Simplytest.me'),
        'text' => new TranslatableMarkup('Vestibulum id ligula porta felis euismod semper. Nulla vitae elit libero, a pharetra augue.'),
        'link' => [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Join the conversation'),
          '#url' => 'https://www.drupal.org/project/issues/simplytest',
        ]
      ],
      'spread' => [
        'title' => new TranslatableMarkup('Spread the word'),
        'text' => new TranslatableMarkup('Vestibulum id ligula porta felis euismod semper. Nulla vitae elit libero, a pharetra augue.'),
        'link' => [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Follow'),
          '#url' => 'https://www.drupal.org/project/issues/simplytest',
        ]
      ],
    ];
    $build = [
      'mount' => [
        '#markup' => Markup::create('<div class="simplytest-react-component" id="root"></div>'),
        '#attached' => [
          'library' => [
            'simplytest_theme/react',
          ],
          'drupalSettings' => [
            // Pass custom launcher values to drupalSettings.
            'launcher' => $request->query->get('launcher', []),
          ],
        ],
      ],
      'triptych' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => 'triptych',
        ],
      ]
    ];
    foreach ($text as $key => $content) {
      $build['triptych'][$key] = [
        '#type' => 'inline_template',
        '#template' => '<div class="col block">
    <h2 class="block__title">{{title}}</h2>
        <div><p>{{text}}</p></div>
        <div><a href="/">Report a problem</a></div>
</div>',
        '#context' => $content,
      ];
    }
    return $build;
  }

  /**
   * Return response for the controller.
   */
  public function projectSelector() {
    return [];
  }

  /**
   * Project launcher service for react.
   */
  public function launchProject(Request $request) {
    $submission = Json::decode($request->getContent());

    // @todo add more validation when ocd button is clicked.
    if ($error = $this->validateSubmission($submission)) {
      return new JsonResponse(['error' => $error]);
    }

    // @todo launch submission for react.
    // $this->instanceManager->launchInstance($submission);

    return new JsonResponse($submission);
  }

  /**
   * Helper method to validate the submitted data.
   */
  private function validateSubmission($data) {
    $project = $data['project'];
    $project = strtolower(trim($project));
    $version = $data['version'];
    $ocd_id = (isset($data['ocd_id'])) ? $data['ocd_id'] : NULL;

    // @todo Flood protection check.

    // @todo add more validation for ocd_id.
    if ($ocd_id) {
      return NULL;
    }

    // Check whether a project shortname was submitted.
    if (empty($project)) {
      return 'Please enter a project shortname to launch a sandbox for.';
    }

    // Before we try to fetch anything, check whether this shortname is valid.
    if (preg_match('/[^a-z_\-0-9]/i', $project)) {
      return 'Please enter a valid shortname of a module, theme or distribution.';
    }

    // Get available project versions.
    $versions = $this->simplytestProjectFetcher->fetchVersions($project);

    // Check whether the submitted project exists.
    if ($versions === FALSE) {
      return t('The selected project shortname %project could not be found.',
        ['%project' => $project]
      );
    }

    // Check whether the selected has any available releases.
    elseif (empty($versions['heads']) && empty($versions['tags'])) {
      return t('The selected project %project has no available releases. (Release cache is cleared once an hour)',
        array('%project' => $project)
      );
    }
    // Check if there was even a version selected for the project.
    elseif (empty($version)) {
      return t('No version was selected for the requested project.',
        array('%project' => $project)
      );
    }
    // Check whether the selected version is a known tag or branch.
    elseif (!in_array($version, $versions['tags']) && !in_array($version, $versions['heads'])) {
      // Even if the selected version is no known tag or branch it's still
      // possible that it's not a version but a specific commit.
      return t('There is no release available with the selected version %version.',
        array('%version' => $version)
      );
    }
  }

}
