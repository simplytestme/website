<?php

namespace Drupal\simplytest_launch;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

final class SimplytestLaunchServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $settings = BootstrapConfigStorageFactory::get()->read('simplytest_launch.settings');
    if (!is_array($settings)) {
      $settings = ['allowed_hosts' => []];
    }
    $allowed_hosts = $settings['allowed_hosts'] ?? [];
    $container->setParameter('simplytest_launch.allowed_hosts', $allowed_hosts);
  }

}
