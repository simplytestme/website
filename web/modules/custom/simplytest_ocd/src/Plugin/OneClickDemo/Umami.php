<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\simplytest_ocd\OneClickDemoInterface;

/**
 * Provides one click demo for umami.
 *
 * @OneClickDemo(
 *   id = "oneclickdemo_umami",
 *   title = @Translation("Umami Demo"),
 *   base_preview_name = "umami",
 * )
 */
class Umami extends Drupal9Base {

  public function getDownloadCommands($parameters): array {
    $commands = [];
    return $commands;
  }

  public function getPatchingCommands($parameters): array {
    return [];
  }

  public function getInstallingCommands($parameters): array {
    $commands = [];
    $commands[] = 'cd ${DOCROOT} && php -d memory_limit=-1 ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y';
    return $commands;
  }

}
