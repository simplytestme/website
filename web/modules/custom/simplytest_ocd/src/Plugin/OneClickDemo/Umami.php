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

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getPatchingCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    $commands[] = 'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini';
    $commands[] = 'a2enmod headers rewrite';
    $commands[] = 'rm -rf "${DOCROOT}"';
    // Pin to Drupal ^10 for now, until ^11 is supported.
    $commands[] = 'composer -n create-project drupal/recommended-project stm --no-install';
    $commands[] = 'cd stm && composer require --no-update drush/drush';
    $commands[] = 'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"';
    return $commands;
  }

  #[\Override]
  public function getInstallingCommands(array $parameters): array {
    $commands = [];
    $commands[] = 'cd ${DOCROOT} && ../vendor/bin/drush si demo_umami --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y';
    return $commands;
  }

}
