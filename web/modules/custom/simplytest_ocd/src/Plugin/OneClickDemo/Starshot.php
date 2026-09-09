<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

/**
 * Provides one click demo for umami.
 *
 * @OneClickDemo(
 *   id = "starshot",
 *   title = @Translation("Drupal CMS"),
 *   base_preview_name = "starshot",
 *   description = @Translation("The new default Drupal, with smart defaults and installable recipes."),
 *   weight = 0,
 *   recommended = TRUE,
 * )
 */
class Starshot extends OneClickDemoBase {

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    $commands[] = 'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini';
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
    // The Drupal CMS installer profile cannot run at the command line: a site
    // template installs a theme partway through, the installer's container is
    // rebuilt without its synthetic services, and Canvas's hooks then fail to
    // load. Installing minimal first and applying the template on the booted
    // site takes the normal container rebuild path. Starter is the template
    // the installer would have picked.
    $commands = [];
    $commands[] = 'cd ${DOCROOT} && ../vendor/bin/drush si minimal --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Drupal CMS Demo"';
    $commands[] = 'cd ${DOCROOT} && ../vendor/bin/drush recipe ../recipes/drupal_cms_starter';
    return $commands;
  }

}
