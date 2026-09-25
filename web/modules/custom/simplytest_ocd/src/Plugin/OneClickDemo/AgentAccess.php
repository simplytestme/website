<?php declare(strict_types=1);

namespace Drupal\simplytest_ocd\Plugin\OneClickDemo;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplytest_ocd\Attribute\OneClickDemo;

/**
 * Drupal CMS with the Agent Access recipe applied.
 *
 * Agent Access connects external AI agents to Drupal over MCP, with OAuth
 * sign-in through a Drupal account. The site is installed from Byte, a site
 * template that ships with Drupal CMS, because the recipe's starter tools
 * only read content and an empty site gives an agent nothing to list.
 *
 * The recipe leaves key generation and the registration endpoint to the site
 * builder. Both are done here so the sandbox's `/mcp` URL works as soon as it
 * launches. The admin account already holds every permission, so no role is
 * granted anything.
 *
 * @see https://www.drupal.org/project/agent_access
 */
#[OneClickDemo(
  id: "oneclickdemo_agent_access",
  title: new TranslatableMarkup("Agent Access"),
  base_preview_name: "agent_access",
  description: new TranslatableMarkup("Drupal CMS, ready for AI agents. Connect one to the site's /mcp URL and sign in as admin."),
  weight: 3,
)]
final class AgentAccess extends OneClickDemoBase {

  /**
   * The packages the recipe needs, as its README lists them.
   *
   * Composer ignores stability flags on transitive dependencies, so each
   * prerelease module is required directly with its own flag.
   */
  private const array PACKAGES = [
    'drupal/tool:^1.0@beta',
    'drupal/tool_belt:^1.0@alpha',
    'drupal/mcp_server:^2.0.0-beta5@beta',
    'drupal/mcp_server_tool_bridge:^1.0.0-beta3@beta',
    'drupal/mcp_server_oauth-mcp_server_oauth:^1.0@alpha',
    'drupal/agent_access:^1.0.0-alpha2@alpha',
  ];

  /**
   * Where the OAuth signing keys live, outside the web root.
   */
  private const string KEYS_DIR = '${TUGBOAT_ROOT}/stm/oauth-keys';

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
    $packages = array_map(escapeshellarg(...), ['drush/drush', ...self::PACKAGES]);
    return [
      'cd "${TUGBOAT_ROOT}/stm" && composer require --no-update ' . implode(' ', $packages),
    ];
  }

  #[\Override]
  public function getPatchingCommands(array $parameters): array {
    return [];
  }

  #[\Override]
  public function getInstallingCommands(array $parameters): array {
    $keys = self::KEYS_DIR;
    return [
      // Installing from the recipe path, not from the Drupal CMS installer
      // profile, for the reason given in SiteTemplate::getInstallingCommands().
      'cd "${DOCROOT}" && ../vendor/bin/drush si ../recipes/byte --db-url=mysql://tugboat:tugboat@mysql:3306/tugboat --account-name=admin --account-pass=admin -y --site-name="Agent Access Demo"',
      'cd "${DOCROOT}" && ../vendor/bin/drush recipe ../recipes/agent_access',
      // A sandbox launched from this demo is a clone, so every sandbox signs
      // tokens with the same keys. That is acceptable for a throwaway site
      // whose admin password is public anyway.
      sprintf('mkdir -p "%1$s" && cd "${DOCROOT}" && ../vendor/bin/drush simple-oauth:generate-keys "%1$s"', $keys),
      sprintf('chown -R www-data:www-data "%s"', $keys),
      sprintf('cd "${DOCROOT}" && ../vendor/bin/drush config:set simple_oauth.settings public_key "%s/public.key" -y', $keys),
      sprintf('cd "${DOCROOT}" && ../vendor/bin/drush config:set simple_oauth.settings private_key "%s/private.key" -y', $keys),
      // Applying the recipe from the command line saves `http://default` as
      // the client registration endpoint. Without a saved value it is built
      // from each request, which is also what a clone on a new hostname needs.
      'cd "${DOCROOT}" && ../vendor/bin/drush config:delete simple_oauth_server_metadata.settings registration_endpoint -y',
    ];
  }

}
