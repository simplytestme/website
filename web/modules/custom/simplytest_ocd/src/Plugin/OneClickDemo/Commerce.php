<?php

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplytest_ocd\Attribute\OneClickDemo;

/**
 * Provides one click demo for commerce.
 */
#[OneClickDemo(
  id: "oneclickdemo_commerce",
  title: new TranslatableMarkup("Commerce Kickstart"),
  base_preview_name: "commerce",
  description: new TranslatableMarkup("A working storefront on Drupal Commerce: catalog, cart and checkout."),
  weight: 1,
)]
class Commerce extends OneClickDemoBase {

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    return [
      'echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/my-php.ini',
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
    $commands[] = 'cd "${DOCROOT}" && mkdir -p sites/default/files/private';
    $commands[] = 'echo \'$settings["file_private_path"] = "sites/default/files/private";\' >> ${DOCROOT}/sites/default/settings.php';
    $commands[] = 'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/commerce_kickstart_demo --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y';
    return $commands;
  }

}
