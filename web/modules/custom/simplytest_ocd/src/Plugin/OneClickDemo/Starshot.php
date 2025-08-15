<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

/**
 * Provides one click demo for umami.
 *
 * @OneClickDemo(
 *   id = "starshot",
 *   title = @Translation("Starshot"),
 *   base_preview_name = "drupal10",
 * )
 */
class Starshot extends OneClickDemoBase {

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    $commands[] = 'docker-php-ext-install opcache';
    $commands[] = 'a2enmod headers rewrite';
    $commands[] = 'rm -rf "${DOCROOT}"';
    return $commands;
  }

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    $commands[] = 'git clone https://git.drupalcode.org/project/drupal_cms.git';
    $commands[] = "find \$TUGBOAT_ROOT/drupal_cms -type d -maxdepth 1 -name 'drupal_cms*' -exec composer config --global repositories.{} path {} ';'";
    $commands[] = 'composer config --global repositories.template path $TUGBOAT_ROOT/drupal_cms/project_template';
    $commands[] = 'composer create-project drupal/drupal-cms-project $TUGBOAT_ROOT/stm --stability=dev';
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
    $commands[] = 'cd ${DOCROOT} && php -d memory_limit=-1 ../vendor/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Drupal CMS Demo"';
    return $commands;
  }

}
