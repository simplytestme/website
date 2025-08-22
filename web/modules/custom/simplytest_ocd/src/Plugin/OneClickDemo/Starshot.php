<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

/**
 * Provides one click demo for umami.
 *
 * @OneClickDemo(
 *   id = "starshot",
 *   title = @Translation("Drupal CMS"),
 *   base_preview_name = "drupal10",
 * )
 */
class Starshot extends OneClickDemoBase {

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    $commands[] = 'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini';
    $commands[] = 'a2enmod headers rewrite';
    $commands[] = 'rm -rf "${DOCROOT}"';
    return $commands;
  }

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    $commands[] = 'composer create-project drupal/cms $TUGBOAT_ROOT/stm';
    $commands[] = 'ln -snf $TUGBOAT_ROOT/stm/web $DOCROOT';
    return $commands;
  }

  #[\Override]
  public function getPatchingCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getInstallingCommands(array $parameters): array {
    $commands = [];
    $commands[] = 'cd ${DOCROOT} && ../vendor/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Drupal CMS Demo"';
    return $commands;
  }

}
