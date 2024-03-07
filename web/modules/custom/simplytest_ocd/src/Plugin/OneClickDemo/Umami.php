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
class Umami extends OneClickDemoBase {

  public function getDownloadCommands($parameters): array {
    return [];
  }

  public function getPatchingCommands($parameters): array {
    return [];
  }

  public function getSetupCommands(array $parameters): array {
    $commands[] = 'docker-php-ext-install opcache';
    $commands[] = 'a2enmod headers rewrite';
    $commands[] = 'rm -rf "${DOCROOT}"';
    $commands[] = 'composer -n create-project drupal/recommended-project stm --no-install';
    $commands[] = 'cd stm && composer require --no-update drush/drush';
    $commands[] = 'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"';
    return $commands;
  }

  public function getInstallingCommands($parameters): array {
    $commands = [];
    $commands[] = 'cd ${DOCROOT} && php -d memory_limit=-1 ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y';
    return $commands;
  }

}
