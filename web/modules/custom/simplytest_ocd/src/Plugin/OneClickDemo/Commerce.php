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

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    return [
      'docker-php-ext-install opcache',
      'docker-php-ext-install bcmath',
      'a2enmod headers rewrite',
      'rm -rf "${DOCROOT}"',
    ];
  }

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    $commands = [
      // @todo the base preview doesn't have the `commerce` dir?
      'cd "${TUGBOAT_ROOT}" && composer create-project centarro/commerce-kickstart-project stm --no-install --stability dev --no-interaction',
      'cd "${TUGBOAT_ROOT}/stm" && composer require --no-update drupal/commerce_demo:^3.0',
      // Remove bin-dir customization,
      'cd "${TUGBOAT_ROOT}/stm" && composer config bin-dir --unset',
      'cd "${TUGBOAT_ROOT}/stm" && composer install',
      'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"'
    ];
    // $commands[] = 'cd "${TUGBOAT_ROOT}"/commerce && composer update --no-ansi';
    return $commands;
  }

  #[\Override]
  public function getPatchingCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getInstallingCommands(array $parameters): array {
    $commands = [];
    $commands[] = 'echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> ${DOCROOT}/sites/default/settings.php';
    $commands[] = 'cd "${DOCROOT}" && ../vendor/bin/drush si --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y';
    $commands[] = 'cd "${DOCROOT}" && ../vendor/bin/drush en commerce_demo -y';
    return $commands;
  }

}
