<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplytest_ocd\Attribute\OneClickDemo;

/**
 * Dashi, the food magazine demo for Drupal CMS.
 *
 * Dashi ports core's Umami demo to a Drupal CMS site template, with its
 * content in English and Spanish and its pages built in Canvas. It replaces
 * the Umami demo, which Drupal 12 no longer ships.
 *
 * It is also in the site template picker, where a launch builds on the shared
 * Drupal CMS base and takes a minute or more. Here it owns its base, so a
 * launch is a clone of the installed site.
 *
 * @see https://www.drupal.org/project/dashi
 */
#[OneClickDemo(
  id: "oneclickdemo_dashi",
  title: new TranslatableMarkup("Dashi"),
  base_preview_name: "dashi",
  description: new TranslatableMarkup("The food magazine demo for Drupal CMS, in English and Spanish. Replaces core's Umami."),
  weight: 2,
  slug: "dashi",
  screenshot: "images/dashi.webp",
)]
final class Dashi extends OneClickDemoBase {

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    return [
      // Applying a recipe holds the whole config tree while it validates, which
      // wants more memory than the image ships with.
      'echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/my-php.ini',
      'rm -rf "${DOCROOT}"',
      'cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/cms:^2 stm --no-install',
      'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
    ];
  }

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    return [
      'cd "${TUGBOAT_ROOT}/stm" && composer require --no-update drush/drush drupal/dashi:^1',
    ];
  }

  #[\Override]
  public function getPatchingCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getInstallingCommands(array $parameters): array {
    return [
      // Installing from the recipe path, not from the Drupal CMS installer
      // profile, for the reason given in SiteTemplate::getInstallingCommands().
      'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/dashi --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name=Dashi',
    ];
  }

}
