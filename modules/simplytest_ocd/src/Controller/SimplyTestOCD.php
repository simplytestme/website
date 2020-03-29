<?php

namespace Drupal\simplytest_ocd\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simplytest_ocd\SimplyTestOCDPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for simplytest ocd module routes.
 */
class SimplyTestOCD extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The simplytest_ocd plugin manager.
   *
   * @var \Drupal\simplytest_ocd\SimplyTestOCDPluginManager
   */
  protected $simplytestOCDManager;

  /**
   * Constructs a new SimplyTestOCD object.
   *
   * @param \Drupal\simplytest_ocd\SimplyTestOCDPluginManager $simplytest_ocd_manager
   *   The simplytest_ocd plugin manager.
   */
  public function __construct(SimplyTestOCDPluginManager $simplytest_ocd_manager) {
    $this->simplytestOCDManager = $simplytest_ocd_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.simplytest_ocd')
    );
  }

  /**
   * It fulfills autocomplete request of a project.
   */
  public function ocdInfo() {
    $ocds = [];

    foreach ($this->simplytestOCDManager->getDefinitions() as $id => $definition) {
      $ocds[$id] = [
        'title' => $definition['title'],
        'ocd_id' => $definition['ocd_id'],
        'theme_key' => $definition['theme_key'],
      ];
    }

    return new JsonResponse($ocds);
  }

}
