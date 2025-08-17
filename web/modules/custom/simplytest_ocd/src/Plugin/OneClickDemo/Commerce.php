<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

/**
 * Provides one click demo for commerce.
 *
 * @OneClickDemo(
 *   id = "oneclickdemo_commerce",
 *   title = @Translation("Commerce Kickstart Demo"),
 *   base_preview_name = "commerce"
 * )
 */
class Commerce extends OneClickDemoBase {

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    return [
      'docker-php-ext-install opcache',
      'docker-php-ext-install bcmath',
      'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini',
      'a2enmod headers rewrite',
      'rm -rf "${DOCROOT}"',
    ];
  }

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    $commands = [
      'cd "${TUGBOAT_ROOT}" && composer create-project centarro/commerce-kickstart-project stm --no-install --stability dev --no-interaction',
      // Remove bin-dir customization,
      'cd "${TUGBOAT_ROOT}/stm" && composer config bin-dir --unset',
      'cd "${TUGBOAT_ROOT}/stm" && composer install',
      'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"'
    ];
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
    return $commands;
  }

}
