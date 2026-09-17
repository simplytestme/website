<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplytest_ocd\Attribute\OneClickDemo;
use Drupal\simplytest_ocd\Plugin\Derivative\SiteTemplateDeriver;
use Drupal\simplytest_ocd\SiteTemplateInfo;

/**
 * Launches a Drupal CMS site template.
 *
 * One instance per template in the curated list, all of them building on the
 * same base preview: Drupal CMS with Composer run but no site installed. The
 * launch requires the template's own package on top, which is a handful of
 * packages rather than the whole tree, and then installs from the recipe.
 *
 * Measured on Tugboat: the base takes about two minutes and is built once a
 * day, a launch takes a minute to a minute and a half. Most of that is the
 * recipe apply, which is database work no base preview can cache away.
 */
#[OneClickDemo(
  id: "site_template",
  title: new TranslatableMarkup("Site template"),
  base_preview_name: SiteTemplate::BASE_PREVIEW,
  description: new TranslatableMarkup("A Drupal CMS site template."),
  weight: 10,
  clone_base: FALSE,
  group: "site_template",
  deriver: SiteTemplateDeriver::class,
)]
final class SiteTemplate extends OneClickDemoBase {

  /**
   * The base preview every site template builds on.
   *
   * Drupal CMS, Composer-installed but never site-installed: vendor, core and
   * every bundled recipe's dependencies are on disk, with no database. A
   * launch requires its own template on top, which is a handful of packages
   * rather than the whole tree.
   *
   * It is deliberately not a demo's base. A demo's base is the finished demo
   * and a launch clones it; this one is a starting point every template
   * shares, so a launch builds on it instead.
   */
  public const string BASE_PREVIEW = 'drupal_cms';

  /**
   * The template this instance launches.
   */
  private function template(): SiteTemplateInfo {
    $definition = $this->getPluginDefinition();
    assert(is_array($definition) && isset($definition['template']));
    return SiteTemplateInfo::fromArray($definition['template']);
  }

  #[\Override]
  public function getSetupCommands(array $parameters): array {
    // Applying a recipe holds the whole config tree while it validates, which
    // wants more memory than the image ships with.
    $commands = ['echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/my-php.ini'];

    // The base preview already holds Drupal CMS. Building it here is the
    // fallback for when no base is usable, so that a launch is slow rather
    // than broken.
    $create = [
      'rm -rf "${DOCROOT}"',
      'cd "${TUGBOAT_ROOT}" && composer -n create-project drupal/cms:^2 stm --no-install',
      'cd "${TUGBOAT_ROOT}/stm" && composer require --no-update drush/drush',
      'cd "${TUGBOAT_ROOT}/stm" && composer install --no-ansi',
      'ln -snf "${TUGBOAT_ROOT}/stm/web" "${DOCROOT}"',
    ];
    $commands[] = sprintf(
      '[ -f "${TUGBOAT_ROOT}/stm/composer.json" ] && echo "Reusing Drupal CMS from the base preview" || (%s)',
      implode(' && ', $create),
    );
    return $commands;
  }

  #[\Override]
  public function getDownloadCommands(array $parameters): array {
    $template = $this->template();
    return [
      // Requiring a recipe unpacks it: its dependencies move into the root
      // composer.json and the package itself is dropped, while the recipe
      // stays in recipes/ so it can be applied. Templates that ship with the
      // Drupal CMS project template are already here and cost nothing.
      sprintf('cd "${TUGBOAT_ROOT}/stm" && composer require %s --no-ansi', $template->package),
      // Recipes required by the base are not unpacked by `composer install`,
      // only by `composer require`, so the base leaves some packed.
      'cd "${TUGBOAT_ROOT}/stm" && composer drupal:recipe-unpack --no-ansi',
    ];
  }

  #[\Override]
  public function getPatchingCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getInstallingCommands(array $parameters): array {
    $template = $this->template();
    return [
      // Installing from the recipe path, not from the Drupal CMS installer
      // profile: that profile installs a theme partway through, which rebuilds
      // the installer's container without its synthetic services and leaves
      // Canvas unable to load its hooks.
      sprintf(
        'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/%s --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name=%s',
        $template->recipe,
        escapeshellarg($template->name),
      ),
    ];
  }

}
