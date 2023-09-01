<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

/**
 * Provides one click demo for commerce.
 *
 * This directly extends OneClickDemoBase since it has its own Composer template
 * for the demo.
 *
 * @OneClickDemo(
 *   id = "oneclickdemo_commerce",
 *   title = @Translation("Drupal Commerce Demo"),
 *   base_preview_name = "commerce"
 * )
 */
class Commerce extends OneClickDemoBase {

  public function getSetupCommands(array $parameters): array {
    return [
      'docker-php-ext-install opcache',
      'docker-php-ext-install bcmath',
      'a2enmod headers rewrite',
      'rm -rf "${DOCROOT}"',
    ];
  }

  public function getDownloadCommands($parameters): array {
    $commands = [
      // @todo the base preview doesn't have the `commerce` dir?
      'cd "${TUGBOAT_ROOT}" && composer create-project drupalcommerce/demo-project stm --no-install --stability dev --no-interaction',
      'cd "${TUGBOAT_ROOT}" && composer config --global allow-plugins true && composer install',
      'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"'
    ];
    // $commands[] = 'cd "${TUGBOAT_ROOT}"/commerce && composer update --no-ansi';
    return $commands;
  }

  public function getPatchingCommands($parameters): array {
    return [];
  }

  public function getInstallingCommands($parameters): array {
    $commands = [];
    $commands[] = 'cd "${DOCROOT}" && php -d memory_limit=-1 ../bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y';
    return $commands;
  }

}
